#!/usr/bin/env bash
#
# Tests for tests/tools/diff-coverage.sh
#
# The defect this guards against: the changed-lines coverage check read
# PHPUnit's Clover report, which diff-cover does not understand, so it
# found no line in any diff and passed on every pull request. Each case
# builds a throwaway git repository with one changed PHP file and a
# hand-written coverage report, and checks the exit status of the script.
#
# Needs diff-cover (pip install diff-cover==9.2.0), so it skips itself
# when it is not installed. Never touches the project repository.
#
# Usage: tests/bin/diff-coverage-test.sh
#
set -uo pipefail

readonly PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# Overridable to run these tests against a mutated copy of the script.
readonly SCRIPT="${DIFF_COVERAGE_SCRIPT:-${PROJECT_DIR}/tests/tools/diff-coverage.sh}"

PASSED=0
FAILED=0

pass() { printf '  \033[0;32mok\033[0m   %s\n' "$1"; PASSED=$((PASSED + 1)); }
fail() { printf '  \033[0;31mFAIL\033[0m %s\n' "$1"; FAILED=$((FAILED + 1)); }
skip() { printf '  \033[0;33mskip\033[0m %s\n' "$1"; }

printf '\ndiff-coverage.sh\n'

if ! command -v diff-cover >/dev/null 2>&1; then
    skip "diff-cover is not installed (pip install diff-cover==9.2.0)"
    exit 0
fi

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

# Isolate the fixture repository from the user's git configuration
# (commit signing, hooks, default branch).
export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1
export GIT_AUTHOR_NAME=test GIT_AUTHOR_EMAIL=test@example.invalid
export GIT_COMMITTER_NAME=test GIT_COMMITTER_EMAIL=test@example.invalid

REPO="${WORK}/repo"
mkdir -p "${REPO}/src"
git -C "$REPO" init --quiet --initial-branch=base
printf '<?php\n\n' > "${REPO}/src/Foo.php"
git -C "$REPO" add src/Foo.php
git -C "$REPO" commit --quiet -m base
git -C "$REPO" switch --quiet -c change
# Lines 3 and 4 are the changed executable lines.
printf '<?php\n\n$a = 1;\nreturn $a;\n' > "${REPO}/src/Foo.php"
git -C "$REPO" commit --quiet -am change

# cobertura SOURCE HITS > FILE
cobertura() {
    cat <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE coverage SYSTEM "http://cobertura.sourceforge.net/xml/coverage-04.dtd">
<coverage line-rate="0" branch-rate="0" lines-covered="0" lines-valid="2" branches-covered="0" branches-valid="0" complexity="0" version="0.4" timestamp="0">
  <sources><source>$1</source></sources>
  <packages><package name="global" line-rate="0" branch-rate="0" complexity="0"><classes>
    <class name="Foo" filename="Foo.php" line-rate="0" branch-rate="0" complexity="0"><methods/><lines>
      <line number="3" hits="$2"/>
      <line number="4" hits="$2"/>
    </lines></class>
  </classes></package></packages>
</coverage>
EOF
}

# run REPORT -> exit status of the script, its output in $WORK/out
run() {
    (cd "$REPO" && "$SCRIPT" "$1" base 70 "${WORK}/diff-coverage.md") > "${WORK}/out" 2>&1
}

cobertura "${REPO}/src" 1 > "${WORK}/covered.xml"
if run "${WORK}/covered.xml"; then
    pass "passes when the changed lines are covered"
else
    fail "passes when the changed lines are covered: $(cat "${WORK}/out")"
fi

cobertura "${REPO}/src" 0 > "${WORK}/uncovered.xml"
if run "${WORK}/uncovered.xml"; then
    fail "fails when the changed lines are not covered"
elif grep -q 'Missing lines 3-4' "${WORK}/out"; then
    pass "fails when the changed lines are not covered, and names them"
else
    fail "fails when the changed lines are not covered, and names them: $(cat "${WORK}/out")"
fi

# The report PHPUnit writes with --coverage-clover: diff-cover reads no
# line from it and passes, which is the original defect.
cat > "${WORK}/clover.xml" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="0">
  <project timestamp="0">
    <file name="${REPO}/src/Foo.php">
      <line num="3" type="stmt" count="0"/>
      <line num="4" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
EOF
if run "${WORK}/clover.xml"; then
    fail "refuses PHPUnit's Clover report"
elif grep -q 'not a Cobertura report' "${WORK}/out"; then
    pass "refuses PHPUnit's Clover report"
else
    fail "refuses PHPUnit's Clover report: $(cat "${WORK}/out")"
fi

# A report generated in the Docker container (/var/www/app) and checked
# on the host: no source path matches, diff-cover would pass.
cobertura /var/www/app/src 0 > "${WORK}/foreign.xml"
if run "${WORK}/foreign.xml"; then
    fail "refuses a report generated under another path"
elif grep -q 'does not exist here' "${WORK}/out"; then
    pass "refuses a report generated under another path"
else
    fail "refuses a report generated under another path: $(cat "${WORK}/out")"
fi

mkdir -p "${WORK}/elsewhere/src"
cobertura "${WORK}/elsewhere/src" 0 > "${WORK}/outside.xml"
if run "${WORK}/outside.xml"; then
    fail "refuses a report whose source is outside the checkout"
elif grep -q 'outside this checkout' "${WORK}/out"; then
    pass "refuses a report whose source is outside the checkout"
else
    fail "refuses a report whose source is outside the checkout: $(cat "${WORK}/out")"
fi

printf '\n%d passed, %d failed\n' "$PASSED" "$FAILED"
[[ $FAILED -eq 0 ]]
