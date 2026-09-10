#!/bin/sh

# One-time bootstrap: point git at the repo-local .githooks directory so the
# commit-msg validator runs on every commit. Idempotent — safe to re-run.

set -u

root="$(git rev-parse --show-toplevel 2>/dev/null)"

if [ -z "$root" ]; then
    echo "error: not inside a git repository" >&2
    exit 1
fi

cd "$root" || exit 1

if [ ! -x "$root/.githooks/commit-msg" ]; then
    echo "warning: $root/.githooks/commit-msg is missing or not executable" >&2
fi

git config core.hooksPath .githooks

echo "git hooks enabled: core.hooksPath set to '.githooks'"
echo "commit messages are now validated against docs/COMMIT_CONVENTION.md"
echo "bypass with: git commit --no-verify"