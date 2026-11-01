#!/usr/bin/env bash
#
# Coverage of the lines a branch changes, read from the Cobertura report
# of `make test-coverage`. Fails below THRESHOLD % of the changed
# executable lines. Needs diff-cover (pip install diff-cover==9.2.0).
#
# diff-cover silently passes ("No lines with coverage information in this
# diff") when it cannot match the report to the diff, which happened on
# every pull request while the check read PHPUnit's Clover report: that
# file is not the Atlassian Clover format diff-cover reads. Both ways of
# getting there are refused here instead:
#   - a report that is not Cobertura;
#   - a report whose <source> directories do not exist inside this
#     checkout (generated in a container under another path, or on
#     another machine).
#
# Usage: tests/tools/diff-coverage.sh REPORT COMPARE_BRANCH THRESHOLD MARKDOWN_OUT
#
set -euo pipefail

if [[ $# -ne 4 ]]; then
    echo "Usage: $0 REPORT COMPARE_BRANCH THRESHOLD MARKDOWN_OUT" >&2
    exit 2
fi
readonly report=$1 compare_branch=$2 threshold=$3 markdown_out=$4

if [[ ! -f "$report" ]]; then
    echo "No coverage report at $report: run make test-coverage first." >&2
    exit 1
fi

if ! grep -q 'cobertura.sourceforge.net/xml/coverage' "$report"; then
    echo "$report is not a Cobertura report: diff-cover would match no line and pass." >&2
    exit 1
fi

toplevel=$(git rev-parse --show-toplevel)
sources=$(sed -n 's:.*<source>\(.*\)</source>.*:\1:p' "$report")
if [[ -z "$sources" ]]; then
    echo "$report declares no <source> directory." >&2
    exit 1
fi
while IFS= read -r source; do
    if [[ ! -d "$source" ]]; then
        echo "$report was generated under $source, which does not exist here:" >&2
        echo "diff-cover would match no line and pass. Generate the report in this checkout." >&2
        exit 1
    fi
    resolved=$(cd "$source" && pwd -P)
    if [[ "$resolved/" != "$(cd "$toplevel" && pwd -P)/"* ]]; then
        echo "$report was generated under $source, outside this checkout ($toplevel)." >&2
        exit 1
    fi
done <<< "$sources"

mkdir -p "$(dirname "$markdown_out")"
status=0
diff-cover "$report" \
    --compare-branch="$compare_branch" \
    --fail-under="$threshold" \
    --markdown-report="$markdown_out" || status=$?
cat "$markdown_out" || true
exit "$status"
