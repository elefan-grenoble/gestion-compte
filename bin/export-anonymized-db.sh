#!/usr/bin/env bash
#
# Produce an anonymized SQL dump that is safe to hand to a developer.
#
# The source database is never written to. A raw dump is restored into a
# scratch database, anonymized there, dumped back out, and only then —
# once it has passed verification — moved to its destination.
#
# Three gates, all blocking:
#
#   1. schema coverage   app:anonymize refuses to run if the manifest does
#                        not classify every column it finds
#   2. leak scan         the produced dump is scanned; any finding and the
#                        file is destroyed instead of delivered
#   3. restore check     the dump is restored into a second scratch
#                        database, so a corrupt artifact is caught here
#                        rather than by whoever receives it
#
# The output file is only ever created by the final move. An interrupted
# or failed run leaves no partial artifact behind that somebody could
# mistake for a finished one.
#
set -euo pipefail

readonly SCRIPT_NAME="${0##*/}"
readonly PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# shellcheck source=bin/lib/dump-sanitize.sh
source "${PROJECT_DIR}/bin/lib/dump-sanitize.sh"
# shellcheck source=bin/lib/check-privileges.sh
source "${PROJECT_DIR}/bin/lib/check-privileges.sh"

usage() {
    cat <<USAGE
Usage: ${SCRIPT_NAME} --output <file> [--input <dump.sql>] [options]

  --output <file>     where to write the anonymized dump (required)
  --input <file>      raw SQL dump to anonymize; omit to dump the database
                      DATABASE_URL points at
  --password <pass>   the password every exported account ends up with
                      (default: Password123). Passed to both the
                      anonymizing and the verifying step, which have to
                      agree or verification rejects a sound export.
  --canary <literal>  a value known to be real; verification fails if it
                      survives. Repeatable.
  --allow <literal>   a value deliberately kept, exempt from the pattern
                      rules (an organisation contact address, say).
                      Repeatable.
  --keep-scratch      do not drop the scratch databases (debugging)
  --no-restore-check  skip gate 3
  --skip-privilege-check
                      skip the upfront check of what DATABASE_URL's
                      account can do (see doc/anonymized-export.md);
                      needed when the account holds its privileges
                      through a role, which the check cannot see
  -h, --help          this text

Requires DATABASE_URL in the environment, pointing at the source database:

  export DATABASE_URL='mysql://user:pass@host:3306/dbname'

The source database is only ever read from. See
doc/anonymized-export.md for the privileges that account needs.
USAGE
}

die() {
    printf '%s: %s\n' "${SCRIPT_NAME}" "$1" >&2
    exit 1
}

log() {
    printf '\033[1;34m==>\033[0m %s\n' "$1"
}

# --- arguments ------------------------------------------------------

OUTPUT=""
INPUT=""
PASSWORD="Password123"
KEEP_SCRATCH=0
RESTORE_CHECK=1
SKIP_PRIVILEGE_CHECK=0
CANARIES=()
ALLOWED=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --output)               OUTPUT="${2:-}"; shift 2 ;;
        --input)                INPUT="${2:-}"; shift 2 ;;
        --password)             PASSWORD="${2:-}"; shift 2 ;;
        --canary)               CANARIES+=("${2:-}"); shift 2 ;;
        --allow)                ALLOWED+=("${2:-}"); shift 2 ;;
        --keep-scratch)         KEEP_SCRATCH=1; shift ;;
        --no-restore-check)     RESTORE_CHECK=0; shift ;;
        --skip-privilege-check) SKIP_PRIVILEGE_CHECK=1; shift ;;
        -h|--help)              usage; exit 0 ;;
        *)                      usage >&2; die "unknown argument '$1'" ;;
    esac
done

[[ -n "${OUTPUT}" ]] || { usage >&2; die "--output is required"; }
[[ -n "${PASSWORD}" ]] || die "--password cannot be empty"
[[ -z "${INPUT}" || -r "${INPUT}" ]] || die "cannot read input dump '${INPUT}'"
[[ -n "${DATABASE_URL:-}" ]] || die "DATABASE_URL is not set (see --help)"

# --- connection details ---------------------------------------------
# Parsed from DATABASE_URL rather than asked for separately, so there is
# only one place a credential can be wrong. Nothing here is ever echoed.
#
# Validated before anything else is looked at, so that a malformed
# invocation reports the thing that is actually wrong rather than
# whichever unrelated precondition happens to be checked first.

url_part() { printf '%s' "${DATABASE_URL}" | sed -nE "$1"; }

DB_USER="$(url_part 's#^[a-z0-9+]+://([^:/@]+).*#\1#p')"
DB_PASS="$(url_part 's#^[a-z0-9+]+://[^:/@]+:([^@]*)@.*#\1#p')"
DB_HOST="$(url_part 's#^[a-z0-9+]+://[^@]+@([^:/?]+).*#\1#p')"
DB_PORT="$(url_part 's#^[a-z0-9+]+://[^@]+@[^:/?]+:([0-9]+).*#\1#p')"
DB_NAME="$(url_part 's#^[a-z0-9+]+://[^@]+@[^/]+/([^?]+).*#\1#p')"

[[ -n "${DB_NAME}" ]] || die "could not read a database name out of DATABASE_URL"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

# --- client detection -----------------------------------------------
# MariaDB renamed its clients and, from 11.x on, dropped the mysql* names
# altogether; older installs only have those. Each binary is resolved on
# its own rather than as a pair, because the versions that renamed them
# did not do so in lockstep.

pick_binary() {
    local candidate
    for candidate in "$@"; do
        if command -v "${candidate}" >/dev/null 2>&1; then
            printf '%s' "${candidate}"
            return 0
        fi
    done
    return 1
}

MYSQL_BIN="$(pick_binary mariadb mysql)" \
    || die "no client found on PATH (looked for: mariadb, mysql)"
DUMP_BIN="$(pick_binary mariadb-dump mysqldump)" \
    || die "no dump tool found on PATH (looked for: mariadb-dump, mysqldump)"

# Credentials go to the clients through a private option file: passing
# them as arguments would expose them in the process list to every other
# user on the machine.
CREDENTIALS_FILE="$(mktemp)"
chmod 600 "${CREDENTIALS_FILE}"

# Quoted, with backslashes and quotes escaped: option files treat an
# unquoted '#' as the start of a comment, which would truncate the value
# and fail authentication for a reason nothing in the output would
# explain.
escaped_password="$(printf '%s' "${DB_PASS}" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"

cat > "${CREDENTIALS_FILE}" <<CNF
[client]
host=${DB_HOST}
port=${DB_PORT}
user=${DB_USER}
password="${escaped_password}"
CNF

STAMP="$(date +%Y%m%d%H%M%S)$$"
SCRATCH_DB="${DB_NAME}_anon_${STAMP}"
VERIFY_DB="${DB_NAME}_verify_${STAMP}"
WORK_DIR="$(mktemp -d)"
chmod 700 "${WORK_DIR}"
STAGED_DUMP="${WORK_DIR}/staged.sql"

mysql_run() { "${MYSQL_BIN}" --defaults-extra-file="${CREDENTIALS_FILE}" "$@"; }

cleanup() {
    local status=$?
    if [[ ${KEEP_SCRATCH} -eq 0 ]]; then
        mysql_run -e "DROP DATABASE IF EXISTS \`${SCRATCH_DB}\`; DROP DATABASE IF EXISTS \`${VERIFY_DB}\`;" 2>/dev/null || true
    else
        printf 'scratch databases kept: %s, %s\n' "${SCRATCH_DB}" "${VERIFY_DB}" >&2
    fi
    rm -rf -- "${WORK_DIR}"
    rm -f -- "${CREDENTIALS_FILE}"
    exit "${status}"
}
trap cleanup EXIT INT TERM

# --- privilege check --------------------------------------------------
# Before anything is created: an under-privileged account should fail
# with a list of what it is missing, not partway through with whichever
# SQL error the missing privilege happens to produce first. Runs after
# the trap above is armed, so a refusal here still cleans up the
# credentials file. See bin/lib/check-privileges.sh for what is checked
# and why, and doc/anonymized-export.md for the GRANT statements this
# expects.

if [[ ${SKIP_PRIVILEGE_CHECK} -eq 0 ]]; then
    grants="$(mysql_run --skip-column-names --batch -e 'SHOW GRANTS FOR CURRENT_USER()' 2>&1)" \
        || die "could not read privileges for the DATABASE_URL account: ${grants}"

    missing="$(check_export_privileges "${DB_NAME}" <<< "${grants}")" || true
    if [[ -n "${missing}" ]]; then
        printf 'the DATABASE_URL account is missing:\n%s\n' "${missing}" >&2
        printf 'see doc/anonymized-export.md, or pass --skip-privilege-check\n' >&2
        exit 1
    fi
fi

# --- 0. load the source into a scratch database ----------------------

log "Creating scratch database ${SCRATCH_DB}"
mysql_run -e "CREATE DATABASE \`${SCRATCH_DB}\` CHARACTER SET utf8mb4"

if [[ -n "${INPUT}" ]]; then
    log "Restoring ${INPUT} into the scratch database"
    mysql_run "${SCRATCH_DB}" < "${INPUT}"
else
    # Piped through dump_sanitize for the same reason as the final dump
    # below (see its comment): a view whose DEFINER names an account
    # absent from this server — the case for any view restored from
    # production — is dumped with its table references qualified by
    # `${DB_NAME}`. Restored bare into a scratch database of a different
    # name, that qualification points at a database that does not exist
    # there, and the restore fails with "Unknown column". Every source
    # with such a view was born unrestorable until this pass ran.
    log "Dumping ${DB_NAME} into the scratch database (source is only read)"
    "${DUMP_BIN}" --defaults-extra-file="${CREDENTIALS_FILE}" \
        --single-transaction --quick --routines --events \
        "${DB_NAME}" | dump_sanitize "${DB_NAME}" | mysql_run "${SCRATCH_DB}"
fi

SCRATCH_URL="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${SCRATCH_DB}"

# --- 1. anonymize (gate 1 runs inside the command) -------------------

log "Anonymizing the copy"
DATABASE_URL="${SCRATCH_URL}" php "${PROJECT_DIR}/bin/console" app:anonymize \
    --force --no-interaction --password "${PASSWORD}"

# --- 2. dump, then verify before anything is delivered ---------------

# Piped through dump_sanitize so the artifact does not carry the scratch
# database's generated name — that database is dropped when this script
# exits, so a view still qualified with it would be unrestorable for
# everyone, starting with gate 3 below.
log "Dumping the anonymized copy"
"${DUMP_BIN}" --defaults-extra-file="${CREDENTIALS_FILE}" \
    --single-transaction --quick --routines --events \
    "${SCRATCH_DB}" \
    | dump_sanitize "${SCRATCH_DB}" > "${STAGED_DUMP}"

VERIFY_ARGS=(--password "${PASSWORD}")
for canary in ${CANARIES+"${CANARIES[@]}"}; do VERIFY_ARGS+=(--canary "${canary}"); done
for allowed in ${ALLOWED+"${ALLOWED[@]}"}; do VERIFY_ARGS+=(--allow "${allowed}"); done

log "Verifying the artifact"
if ! php "${PROJECT_DIR}/bin/console" app:anonymize:verify "${STAGED_DUMP}" ${VERIFY_ARGS+"${VERIFY_ARGS[@]}"}; then
    # The header is zeroed before unlinking so that a recovered fragment
    # is not a usable dump. This is tidiness, not secure erasure — on a
    # journalling or copy-on-write filesystem the original blocks may well
    # survive, so treat a failed export as data that existed on disk.
    dd if=/dev/zero of="${STAGED_DUMP}" bs=1M count=1 conv=notrunc 2>/dev/null || true
    rm -f -- "${STAGED_DUMP}"
    die "verification failed — no dump was written to '${OUTPUT}'"
fi

# --- 3. prove the artifact restores ----------------------------------

if [[ ${RESTORE_CHECK} -eq 1 ]]; then
    log "Checking the dump restores"
    mysql_run -e "CREATE DATABASE \`${VERIFY_DB}\` CHARACTER SET utf8mb4"
    mysql_run "${VERIFY_DB}" < "${STAGED_DUMP}"

    restored="$(mysql_run -N -B -e \
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${VERIFY_DB}'")"
    [[ "${restored}" -gt 0 ]] || die "the dump restored into an empty schema"
    log "Restored ${restored} tables"
fi

# --- 4. deliver ------------------------------------------------------

umask 077
mv -- "${STAGED_DUMP}" "${OUTPUT}"
chmod 600 -- "${OUTPUT}"

log "Anonymized dump written to ${OUTPUT}"
