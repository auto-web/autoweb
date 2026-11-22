#!/bin/sh
# Runs tasks/validate_args.yml on each case of validate_args.cases: the "ok" cases must pass,
# the "fail" cases (malicious or invalid values) must be refused.
# "<ok|fail>@<user>": case skipped when this system user does not exist (created by the CI, see
# .github/workflows/pre-commit.yml).
# Usage (from anywhere): robot/tests/validate_args.sh [ansible-playbook]
set -u

DIR=$(cd "$(dirname "$0")" && pwd)
PLAYBOOK=${1:-ansible-playbook}
errors=0
count=0

while IFS='|' read -r expected description args; do
    expected=$(echo "$expected" | tr -d ' ')
    case "$expected" in
        ''|'#'*) continue ;;
    esac
    description=$(echo "$description" | sed 's/^ *//; s/ *$//')
    case "$expected" in
        *@*)
            needed=${expected#*@}
            expected=${expected%@*}
            if ! getent passwd "$needed" > /dev/null; then
                echo "SKIP  $expected  $description (no user $needed)"
                continue
            fi
            ;;
    esac
    count=$((count + 1))

    # "fail" only when refused by the validation itself (not by a broken case or playbook)
    if output=$("$PLAYBOOK" "$DIR/validate_args.yml" -e "{\"robot_args\": $args}" 2>&1); then
        result=ok
    elif echo "$output" | grep -q 'Invalid account in data/args.json'; then
        result=fail
    else
        result=error
        echo "$output" | tail -5
    fi

    if [ "$result" = "$expected" ]; then
        echo "PASS  $expected  $description"
    else
        echo "FAIL  expected $expected, got $result: $description"
        errors=$((errors + 1))
    fi
done < "$DIR/validate_args.cases"

echo "$count cases, $errors errors"
[ "$count" -gt 0 ] && [ "$errors" -eq 0 ]
