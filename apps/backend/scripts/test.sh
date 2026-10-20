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

vendor/bin/pest "$@" > "$LOG" 2>&1
RC=$?

tr -d '\033' < "$LOG" | sed 's/\[[0-9;]*m//g'
exit $RC
