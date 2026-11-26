#!/bin/sh
# Plain-output Pest runner.
#
# Pest's collision TUI emits ANSI/binary output that vanishes or reads as
# binary when piped, hiding failures in agent/CI logs. This wrapper always
# writes a plain log to storage/logs/pest.log (escape bytes stripped),
# echoes it, and preserves Pest's exit code.
#
#   composer test                       # whole suite
#   composer test -- Modules/Tax/tests  # one module
#
# Grep the log with `grep -a` — some runs still contain bytes that grep
# treats as binary.
set +e
cd "$(dirname "$0")/.."

mkdir -p storage/logs
LOG=storage/logs/pest.log

# The suite is one long-lived PHP process, and the test runner retains
# roughly 100 KB per test (measured on main before tenancy: 90 MB live at
# test 100, 114 MB at test 340). 1,600 tests end near 240 MB of live
# objects plus garbage awaiting collection — past the image's 256M limit,
# which is sized for uploads and not for this. The limit belongs to the
# test process only; CI's two pest steps pass the same flag.
php -d memory_limit=512M vendor/bin/pest "$@" > "$LOG" 2>&1
RC=$?

tr -d '\033' < "$LOG" | sed 's/\[[0-9;]*m//g'
exit $RC
