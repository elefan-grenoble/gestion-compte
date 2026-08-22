#!/usr/bin/env bash
#
# Tests for bin/lib/check-privileges.sh
#
# check_export_privileges reads a block of `SHOW GRANTS` text, not a live
# database, so every case here runs against a fixture string. No
# database, no network, cannot disturb anything.
#
# Usage: tests/bin/check-privileges-test.sh
#
set -uo pipefail

readonly PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# shellcheck source=bin/lib/check-privileges.sh
source "${PROJECT_DIR}/bin/lib/check-privileges.sh"

PASSED=0
FAILED=0

pass() { printf '  \033[0;32mok\033[0m   %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '  \033[0;31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }

printf '\ncheck-privileges.sh\n'

# The exact recipe this test guards: what a scoped, non-root account
# needs to run the export against `some_db`, discovered by running the
# export against a real one and granting exactly what each failure asked
# for. If this ever stops being "complete", the export itself breaks
# against a scoped account — this fixture is meant to track that recipe,
# not drift from it.
readonly COMPLETE_GRANTS='GRANT USAGE ON *.* TO `u`@`%`
GRANT SELECT, SHOW VIEW, EVENT, TRIGGER ON `some_db`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `some_db_anon_%`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `some_db_verify_%`.* TO `u`@`%`
GRANT SET USER ON *.* TO `u`@`%`'

if missing="$(check_export_privileges 'some_db' <<< "${COMPLETE_GRANTS}")"; then
    pass "the exact recipe this project's own doc gives is accepted"
else
    fail "the exact recipe this project's own doc gives is accepted (missing: ${missing})"
fi

# A superuser-shaped grant (root, typical local dev) needs nothing else
# checked — it satisfies every requirement below it by construction.
readonly ROOT_GRANTS="GRANT ALL PRIVILEGES ON *.* TO \`root\`@\`%\` WITH GRANT OPTION"

if missing="$(check_export_privileges 'some_db' <<< "${ROOT_GRANTS}")"; then
    pass "a root-shaped grant short-circuits every check"
else
    fail "a root-shaped grant short-circuits every check (missing: ${missing})"
fi

# Read-only on the source, nothing on the scratch pattern — exactly the
# state this project's own account was left in after the first, naive
# grant, and the shape that motivated this check existing at all.
readonly READ_ONLY_GRANTS='GRANT USAGE ON *.* TO `u`@`%`
GRANT SELECT ON `some_db`.* TO `u`@`%`'

missing="$(check_export_privileges 'some_db' <<< "${READ_ONLY_GRANTS}")" && ok=1 || ok=0
if [[ ${ok} -eq 0 && "${missing}" == *'SHOW VIEW'* && "${missing}" == *'some_db_anon_%'* ]]; then
    pass "read-only on the source reports the scratch-database gap by name"
else
    fail "read-only on the source reports the scratch-database gap by name (got: ${missing})"
fi

# A privilege granted for a different database must not satisfy this
# one — a name-matching bug here would silently pass a misconfigured
# account.
readonly WRONG_DB_GRANTS='GRANT SELECT, SHOW VIEW, EVENT, TRIGGER ON `other_db`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `other_db_anon_%`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `other_db_verify_%`.* TO `u`@`%`
GRANT SET USER ON *.* TO `u`@`%`'

missing="$(check_export_privileges 'some_db' <<< "${WRONG_DB_GRANTS}")" && ok=1 || ok=0
if [[ ${ok} -eq 0 && "${missing}" == *'some_db'* ]]; then
    pass "a grant scoped to a different database does not satisfy this one"
else
    fail "a grant scoped to a different database does not satisfy this one (got: ${missing})"
fi

# A database name that happens to be a regex metacharacter must be
# matched literally, the same property dump_sanitize_escape guards.
readonly DOTTED_DB_GRANTS='GRANT SELECT, SHOW VIEW, EVENT, TRIGGER ON `a.b`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `a.b_anon_%`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `a.b_verify_%`.* TO `u`@`%`
GRANT SET USER ON *.* TO `u`@`%`'

if missing="$(check_export_privileges 'a.b' <<< "${DOTTED_DB_GRANTS}")"; then
    pass "a dot in the database name is matched literally, not as any character"
else
    fail "a dot in the database name is matched literally, not as any character (missing: ${missing})"
fi

if missing="$(check_export_privileges 'axb' <<< "${DOTTED_DB_GRANTS}")"; then
    fail "a dot does not also match a different character (false positive on 'axb')"
else
    pass "a dot does not also match a different character (false positive on 'axb')"
fi

# SUPER stands in for SET USER on servers old enough not to have it.
readonly SUPER_GRANTS='GRANT SELECT, SHOW VIEW, EVENT, TRIGGER ON `some_db`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `some_db_anon_%`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `some_db_verify_%`.* TO `u`@`%`
GRANT SUPER ON *.* TO `u`@`%`'

if missing="$(check_export_privileges 'some_db' <<< "${SUPER_GRANTS}")"; then
    pass "SUPER is accepted in place of SET USER"
else
    fail "SUPER is accepted in place of SET USER (missing: ${missing})"
fi

# Several privileges packed onto one GRANT line, in an order that puts
# the keyword this check looks for anywhere but first — a regex anchored
# on "GRANT <keyword>" would pass COMPLETE_GRANTS above (SELECT happens
# to lead there) and still miss this.
readonly PACKED_GRANTS='GRANT TRIGGER, EVENT, SHOW VIEW, SELECT ON `some_db`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `some_db_anon_%`.* TO `u`@`%`
GRANT ALL PRIVILEGES ON `some_db_verify_%`.* TO `u`@`%`
GRANT SET USER ON *.* TO `u`@`%`'

if missing="$(check_export_privileges 'some_db' <<< "${PACKED_GRANTS}")"; then
    pass "every keyword on a packed GRANT line is found, not just the first"
else
    fail "every keyword on a packed GRANT line is found, not just the first (missing: ${missing})"
fi

printf '\n%d passed, %d failed\n\n' "${PASSED}" "${FAILED}"
[[ ${FAILED} -eq 0 ]]
