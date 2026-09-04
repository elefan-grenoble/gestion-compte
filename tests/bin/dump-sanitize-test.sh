#!/usr/bin/env bash
#
# Tests for bin/lib/dump-sanitize.sh
#
# The defect this guards against is not visible in a dump read on its
# own: it only appears when the dump is restored into a database whose
# name differs from the one it was taken from, which is what the export
# always does. So these tests do a real round trip — dump a schema that
# contains a view, restore it somewhere else, and query the view.
#
# Needs a reachable database, so it skips itself when there is none;
# tests/bin/export-anonymized-db-test.sh covers what can be checked
# without one.
#
# Usage: tests/bin/dump-sanitize-test.sh
#
set -uo pipefail

readonly PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# shellcheck source=bin/lib/dump-sanitize.sh
source "${PROJECT_DIR}/bin/lib/dump-sanitize.sh"

PASSED=0
FAILED=0

pass() { printf '  \033[0;32mok\033[0m   %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '  \033[0;31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
skip() { printf '  \033[0;33mskip\033[0m %s\n' "$1"; }

printf '\ndump-sanitize.sh\n'

# --- the escaping, which needs nothing ------------------------------

if [[ "$(dump_sanitize_escape 'plain_name')" == 'plain_name' ]]; then
    pass "leaves an ordinary database name alone"
else
    fail "leaves an ordinary database name alone"
fi

if [[ "$(dump_sanitize_escape 'od.d[na$me')" == 'od\.d\[na\$me' ]]; then
    pass "escapes the characters sed would otherwise read as syntax"
else
    fail "escapes the characters sed would otherwise read as syntax (got '$(dump_sanitize_escape 'od.d[na$me')')"
fi

# A name that is a regex metacharacter must not match a different name.
if printf 'SELECT `a.c`.`t`.`id` FROM `t`;\n' \
    | dump_sanitize 'a.c' \
    | grep -q 'SELECT `t`.`id`'
then
    pass "a dot in the name is matched literally, not as any character"
else
    fail "a dot in the name is matched literally, not as any character"
fi

if printf 'DEFINER=`someone`@`%%` SQL SECURITY DEFINER VIEW `v` AS select 1;\n' \
    | dump_sanitize 'whatever' \
    | grep -q '^SQL SECURITY DEFINER VIEW'
then
    pass "drops the DEFINER clause"
else
    fail "drops the DEFINER clause"
fi

# --- the round trip, which needs a database -------------------------

DB_URL="${DATABASE_URL:-}"
if [[ -z "${DB_URL}" ]]; then
    skip "round trip (DATABASE_URL is not set)"
    printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
    [[ ${FAILED} -eq 0 ]]
    exit $?
fi

url_part() { printf '%s' "${DB_URL}" | sed -nE "$1"; }
DB_USER="$(url_part 's#^[a-z0-9+]+://([^:/@]+).*#\1#p')"
DB_PASS="$(url_part 's#^[a-z0-9+]+://[^:/@]+:([^@]*)@.*#\1#p')"
DB_HOST="$(url_part 's#^[a-z0-9+]+://[^@]+@([^:/?]+).*#\1#p')"
DB_PORT="$(url_part 's#^[a-z0-9+]+://[^@]+@[^:/?]+:([0-9]+).*#\1#p')"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

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
    skip "round trip (no database client on PATH)"
    printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
    [[ ${FAILED} -eq 0 ]]
    exit $?
fi

CREDENTIALS_FILE="$(mktemp)"
chmod 600 "${CREDENTIALS_FILE}"
cat > "${CREDENTIALS_FILE}" <<CNF
[client]
host=${DB_HOST}
port=${DB_PORT}
user=${DB_USER}
password="${DB_PASS}"
CNF

STAMP="$(date +%Y%m%d%H%M%S)$$"
SRC_DB="dump_sanitize_src_${STAMP}"
DST_DB="dump_sanitize_dst_${STAMP}"
WORK_DIR="$(mktemp -d)"

mysql_run() { "${MYSQL_BIN}" --defaults-extra-file="${CREDENTIALS_FILE}" "$@"; }

cleanup() {
    mysql_run -e "DROP DATABASE IF EXISTS \`${SRC_DB}\`; DROP DATABASE IF EXISTS \`${DST_DB}\`;" 2>/dev/null
    rm -rf -- "${WORK_DIR}"
    rm -f -- "${CREDENTIALS_FILE}"
}
trap cleanup EXIT

if ! mysql_run -e "SELECT 1" >/dev/null 2>&1; then
    skip "round trip (cannot reach the database)"
    printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
    [[ ${FAILED} -eq 0 ]]
    exit $?
fi

mysql_run -e "CREATE DATABASE \`${SRC_DB}\` CHARACTER SET utf8mb4" >/dev/null 2>&1

# Shaped after view_abstract_registration: a view over joined tables, of
# the kind the real schema has and the toy fixtures did not.
#
# The DEFINER has to name an account that does not exist, as a view
# restored from production does. That is what makes the server store the
# table references expanded to `db`.`table`.`column`, which is in turn
# what --single-transaction carries into the dump. With the definer left
# to the current user the server stores them bare and nothing leaks —
# the fixture would then pass while the real case still failed.
mysql_run "${SRC_DB}" <<'SQL' >/dev/null 2>&1
CREATE TABLE registration (id INT PRIMARY KEY, membership_id INT);
CREATE TABLE membership (id INT PRIMARY KEY, main_beneficiary_id INT);
CREATE TABLE beneficiary (id INT PRIMARY KEY, firstname VARCHAR(50), lastname VARCHAR(50));
INSERT INTO beneficiary VALUES (1, 'jean', 'dupont');
INSERT INTO membership VALUES (10, 1);
INSERT INTO registration VALUES (100, 10);
SQL

if ! mysql_run "${SRC_DB}" <<'SQL' >/dev/null 2>&1
CREATE ALGORITHM=TEMPTABLE DEFINER=`absent_definer`@`%` SQL SECURITY DEFINER VIEW view_joined AS
 select concat('1_', `registration`.`id`) AS `id`,
        concat(lower(`beneficiary`.`firstname`), ' ', upper(`beneficiary`.`lastname`)) AS `beneficiary`
 from ((`registration` left join `membership` on((`registration`.`membership_id` = `membership`.`id`)))
       left join `beneficiary` on((`beneficiary`.`id` = `membership`.`main_beneficiary_id`)));
SQL
then
    # Setting a DEFINER other than one's own needs SUPER / SET USER.
    skip "round trip (cannot create a view with a foreign definer)"
    printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
    [[ ${FAILED} -eq 0 ]]
    exit $?
fi

"${DUMP_BIN}" --defaults-extra-file="${CREDENTIALS_FILE}" \
    --single-transaction --quick --routines --events \
    "${SRC_DB}" > "${WORK_DIR}/raw.sql" 2>/dev/null

# The bug, stated as a test: the raw dump names its source database, and
# that name is what makes it unrestorable anywhere else.
if grep -q "\`${SRC_DB}\`\." "${WORK_DIR}/raw.sql"; then
    pass "an unsanitized dump does carry the source database name (the defect is real)"
else
    fail "an unsanitized dump does carry the source database name (the defect is real)"
fi

dump_sanitize "${SRC_DB}" < "${WORK_DIR}/raw.sql" > "${WORK_DIR}/clean.sql"

if grep -q "\`${SRC_DB}\`\." "${WORK_DIR}/clean.sql"; then
    fail "the sanitized dump no longer names the source database"
else
    pass "the sanitized dump no longer names the source database"
fi

mysql_run -e "CREATE DATABASE \`${DST_DB}\` CHARACTER SET utf8mb4" >/dev/null 2>&1

if mysql_run "${DST_DB}" < "${WORK_DIR}/clean.sql" 2>"${WORK_DIR}/restore.err"; then
    pass "the sanitized dump restores into a differently-named database"
else
    fail "the sanitized dump restores into a differently-named database"
    head -3 "${WORK_DIR}/restore.err" | sed 's/^/       /'
fi

# Restoring is not enough: a view can be created and still be unusable.
got="$(mysql_run -N -B "${DST_DB}" -e "SELECT beneficiary FROM view_joined" 2>"${WORK_DIR}/query.err")"
if [[ "${got}" == 'jean DUPONT' ]]; then
    pass "the restored view resolves against its new database"
else
    fail "the restored view resolves against its new database (got '${got}')"
    head -3 "${WORK_DIR}/query.err" | sed 's/^/       /'
fi

printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
[[ ${FAILED} -eq 0 ]]
