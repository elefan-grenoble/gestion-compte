#!/usr/bin/env bash
#
# Tests for bin/export-anonymized-db.sh
#
# Covers the preconditions — the checks that decide whether the script
# will touch a database at all. Every case here is refused before any
# connection is opened, so this needs no database, no fixtures and no
# credentials, and it cannot disturb anything.
#
# The paths that do talk to a database are covered by the PHPUnit suite
# (tests/Functional/Anonymization) and, at runtime, by the export's own
# three gates.
#
# Usage: tests/bin/export-anonymized-db-test.sh
#
set -uo pipefail

readonly SCRIPT_UNDER_TEST="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)/bin/export-anonymized-db.sh"

PASSED=0
FAILED=0
WORK_DIR="$(mktemp -d)"
trap 'rm -rf -- "${WORK_DIR}"' EXIT

pass() { printf '  \033[0;32mok\033[0m   %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '  \033[0;31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }

# Runs the script with a deliberately unusable DATABASE_URL unless the
# case overrides it, so that a precondition which fails to fire shows up
# as a connection error rather than as a silent success.
expect_exit() {
    local description="$1" expected="$2"
    shift 2

    local output status
    output="$("$@" 2>&1)"
    status=$?

    if [[ ${status} -eq ${expected} ]]; then
        pass "${description}"
    else
        fail "${description} (expected exit ${expected}, got ${status})"
        printf '       %s\n' "${output}" | head -3
    fi
}

expect_output_contains() {
    local description="$1" needle="$2"
    shift 2

    local output
    output="$("$@" 2>&1)"

    if [[ "${output}" == *"${needle}"* ]]; then
        pass "${description}"
    else
        fail "${description} (output did not mention '${needle}')"
    fi
}

printf '\nexport-anonymized-db.sh\n'

[[ -x "${SCRIPT_UNDER_TEST}" ]] \
    && pass "the script is executable" \
    || fail "the script is executable"

expect_exit "--help succeeds" 0 \
    env -u DATABASE_URL "${SCRIPT_UNDER_TEST}" --help

expect_output_contains "--help documents that the source is only read" "only ever read" \
    env -u DATABASE_URL "${SCRIPT_UNDER_TEST}" --help

expect_exit "refuses without --output" 1 \
    env DATABASE_URL='mysql://u:p@127.0.0.1:3306/db' "${SCRIPT_UNDER_TEST}"

expect_output_contains "explains that --output is required" "--output is required" \
    env DATABASE_URL='mysql://u:p@127.0.0.1:3306/db' "${SCRIPT_UNDER_TEST}"

expect_exit "refuses without DATABASE_URL" 1 \
    env -u DATABASE_URL "${SCRIPT_UNDER_TEST}" --output "${WORK_DIR}/out.sql"

expect_output_contains "explains that DATABASE_URL is missing" "DATABASE_URL is not set" \
    env -u DATABASE_URL "${SCRIPT_UNDER_TEST}" --output "${WORK_DIR}/out.sql"

expect_exit "refuses an unknown argument" 1 \
    env DATABASE_URL='mysql://u:p@127.0.0.1:3306/db' "${SCRIPT_UNDER_TEST}" --output "${WORK_DIR}/out.sql" --wat

expect_exit "refuses an unreadable --input" 1 \
    env DATABASE_URL='mysql://u:p@127.0.0.1:3306/db' "${SCRIPT_UNDER_TEST}" \
        --output "${WORK_DIR}/out.sql" --input "${WORK_DIR}/does-not-exist.sql"

expect_output_contains "refuses a DATABASE_URL with no database name" "database name" \
    env DATABASE_URL='mysql://u:p@127.0.0.1:3306' "${SCRIPT_UNDER_TEST}" --output "${WORK_DIR}/out.sql"

# The property that matters most: a refused run must not leave anything
# behind that could be mistaken for a finished export.
if [[ -e "${WORK_DIR}/out.sql" ]]; then
    fail "no output file is created when the run is refused"
else
    pass "no output file is created when the run is refused"
fi

printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
[[ ${FAILED} -eq 0 ]]
