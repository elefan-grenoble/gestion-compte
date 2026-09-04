#!/usr/bin/env bash
#
# Regression test for the initial-load half of the view/DEFINER defect
# bin/lib/dump-sanitize.sh already guards (see dump-sanitize-test.sh):
# export-anonymized-db.sh sanitized the *final* dump before delivery but
# restored the *first*, raw dump of a live source straight, unsanitized.
# A source with a view whose DEFINER names an absent account — the case
# for anything restored from production — made that first restore fail
# with "Unknown column", before app:anonymize ever ran.
#
# Runs the real script end to end rather than a snippet, so a future
# change to the load step is held to the same behaviour. The synthetic
# tables are named so they cannot collide with config/anonymization.yaml
# (which does classify tables named plain `registration` etc.): the
# manifest is expected to refuse them, and that refusal is itself the
# signal this test reads (see below).
#
# Needs a reachable database, php, and both database clients (dump +
# restore) on PATH — the same requirements the script itself has. Skips
# itself when any is missing.
#
# Usage: tests/bin/export-anonymized-db-view-restore-test.sh
#
set -uo pipefail

readonly PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly SCRIPT_UNDER_TEST="${PROJECT_DIR}/bin/export-anonymized-db.sh"

PASSED=0
FAILED=0

pass() { printf '  \033[0;32mok\033[0m   %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '  \033[0;31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
skip() { printf '  \033[0;33mskip\033[0m %s\n' "$1"; }

report_and_exit() {
    printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
    [[ ${FAILED} -eq 0 ]]
    exit $?
}

printf '\nexport-anonymized-db.sh (view restore)\n'

DB_URL="${DATABASE_URL:-}"
if [[ -z "${DB_URL}" ]]; then
    skip "view restore (DATABASE_URL is not set)"
    report_and_exit
fi

command -v php >/dev/null 2>&1 || { skip "view restore (no php on PATH)"; report_and_exit; }

pick_binary() {
    local candidate
    for candidate in "$@"; do
        command -v "${candidate}" >/dev/null 2>&1 && { printf '%s' "${candidate}"; return 0; }
    done
    return 1
}

MYSQL_BIN="$(pick_binary mariadb mysql)" || MYSQL_BIN=''
DUMP_BIN="$(pick_binary mariadb-dump mysqldump)" || DUMP_BIN=''
if [[ -z "${MYSQL_BIN}" || -z "${DUMP_BIN}" ]]; then
    skip "view restore (no database client on PATH)"
    report_and_exit
fi

url_part() { printf '%s' "${DB_URL}" | sed -nE "$1"; }
DB_USER="$(url_part 's#^[a-z0-9+]+://([^:/@]+).*#\1#p')"
DB_PASS="$(url_part 's#^[a-z0-9+]+://[^:/@]+:([^@]*)@.*#\1#p')"
DB_HOST="$(url_part 's#^[a-z0-9+]+://[^@]+@([^:/?]+).*#\1#p')"
DB_PORT="$(url_part 's#^[a-z0-9+]+://[^@]+@[^:/?]+:([0-9]+).*#\1#p')"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

CREDENTIALS_FILE="$(mktemp)"
chmod 600 "${CREDENTIALS_FILE}"
cat > "${CREDENTIALS_FILE}" <<CNF
[client]
host=${DB_HOST}
port=${DB_PORT}
user=${DB_USER}
password="${DB_PASS}"
CNF

# Short: this name becomes ${SRC_DB}_anon_<script's own timestamp+pid>
# inside export-anonymized-db.sh, and MySQL/MariaDB reject an identifier
# over 64 characters outright ("Incorrect database name") — the first
# version of this fixture found that the hard way.
STAMP="$(date +%H%M%S)$$"
readonly SRC_DB="zzt_evr_${STAMP}"
WORK_DIR="$(mktemp -d)"

mysql_run() { "${MYSQL_BIN}" --defaults-extra-file="${CREDENTIALS_FILE}" "$@"; }

cleanup() {
    mysql_run -e "DROP DATABASE IF EXISTS \`${SRC_DB}\`;" 2>/dev/null
    rm -rf -- "${WORK_DIR}"
    rm -f -- "${CREDENTIALS_FILE}"
}
trap cleanup EXIT

if ! mysql_run -e "SELECT 1" >/dev/null 2>&1; then
    skip "view restore (cannot reach the database)"
    report_and_exit
fi

mysql_run -e "CREATE DATABASE \`${SRC_DB}\` CHARACTER SET utf8mb4" >/dev/null 2>&1

# Names prefixed so they cannot collide with any real table
# config/anonymization.yaml classifies — the point here is the load
# step, not schema coverage.
mysql_run "${SRC_DB}" <<'SQL' >/dev/null 2>&1
CREATE TABLE zz_test_registration (id INT PRIMARY KEY, membership_id INT);
CREATE TABLE zz_test_membership (id INT PRIMARY KEY, main_beneficiary_id INT);
CREATE TABLE zz_test_beneficiary (id INT PRIMARY KEY, firstname VARCHAR(50), lastname VARCHAR(50));
INSERT INTO zz_test_beneficiary VALUES (1, 'jean', 'dupont');
INSERT INTO zz_test_membership VALUES (10, 1);
INSERT INTO zz_test_registration VALUES (100, 10);
SQL

# Shaped after view_abstract_registration, the real view that surfaced
# this bug: a view over joined tables, with a DEFINER naming an account
# absent from this server, as any view restored from production has.
if ! mysql_run "${SRC_DB}" <<'SQL' >/dev/null 2>&1
CREATE ALGORITHM=TEMPTABLE DEFINER=`absent_definer`@`%` SQL SECURITY DEFINER VIEW zz_test_view_joined AS
 select concat('1_', `zz_test_registration`.`id`) AS `id`,
        concat(lower(`zz_test_beneficiary`.`firstname`), ' ', upper(`zz_test_beneficiary`.`lastname`)) AS `beneficiary`
 from ((`zz_test_registration` left join `zz_test_membership` on((`zz_test_registration`.`membership_id` = `zz_test_membership`.`id`)))
       left join `zz_test_beneficiary` on((`zz_test_beneficiary`.`id` = `zz_test_membership`.`main_beneficiary_id`)));
SQL
then
    # Setting a DEFINER other than one's own needs SUPER / SET USER.
    skip "view restore (cannot create a view with a foreign definer)"
    report_and_exit
fi

# The real run: DATABASE_URL points at SRC_DB, no --input, so the script
# takes the direct-dump branch this test targets.
export DATABASE_URL="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${SRC_DB}"
output="$("${SCRIPT_UNDER_TEST}" --output "${WORK_DIR}/out.sql" --keep-scratch 2>&1)"
status=$?

# The manifest does not know zz_test_*, so a *correct* run still refuses
# — but at gate 1 (schema coverage), after the load succeeded. Before
# the fix, the load itself failed first, with "Unknown column ... in
# 'SELECT'" from the qualified view reference. Which message comes back
# is exactly the regression signal: reaching schema coverage proves the
# scratch database now holds a working copy of the source, foreign
# DEFINER and all.
if [[ "${output}" == *'Unknown column'* ]]; then
    fail "the load restores a view whose DEFINER names an absent account (still hits the pre-fix 'Unknown column' error)"
elif [[ ${status} -ne 0 && "${output}" == *'does not cover this schema'* ]]; then
    pass "the load restores a view whose DEFINER names an absent account (reached schema coverage, past the load step)"
else
    fail "the load restores a view whose DEFINER names an absent account (unexpected output)"
    printf '       %s\n' "${output}" | head -5
fi

# Cleanup for the scratch database this run left behind (--keep-scratch,
# so the failing case above still leaves it inspectable at least once;
# find it by prefix since the script generates its own timestamp).
for db in $(mysql_run -N -B -e "SHOW DATABASES LIKE '${SRC_DB}_anon_%'" 2>/dev/null); do
    mysql_run -e "DROP DATABASE IF EXISTS \`${db}\`" 2>/dev/null
done

report_and_exit
