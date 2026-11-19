<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The exact process-incarnation identity (v12 F1): boot_id + container
 * id + pid + process start time — immune to pid reuse and container
 * recreation, verifiable inside the recorded container. Death proof is
 * necessary but never sufficient for a UID-less intent (worker death ≠
 * remote-task quiescence, v20 F1).
 */
final class OwnerIncarnation
{
    /** @return array<string, int|string> */
    public static function current(): array
    {
        $bootId = self::readFile('/proc/sys/kernel/random/boot_id');
        $containerId = self::containerId();

        return [
            'boot_id' => substr($bootId, 0, 64),
            'container_id' => substr($containerId, 0, 64),
            // pid 1's start time pins the container incarnation (ticks
            // since boot); empty when unavailable (non-Linux dev).
            'container_started_at' => self::processStartTime(1),
            'pid' => getmypid() ?: 0,
            'process_start_time' => self::processStartTime(getmypid() ?: 0),
            'db_connection_id' => self::connectionId(),
            // Diagnostics only — never used for identity decisions.
            'owner' => substr($containerId !== '' ? $containerId : (string) gethostname(), 0, 32)
                .':'.(getmypid() ?: 0)
                .':'.bin2hex(random_bytes(4)),
        ];
    }

    /**
     * Exact-incarnation death proof: the recorded (boot_id, container_id,
     * pid, start_time) no longer resolves to a live process. Fails CLOSED
     * — unreadable /proc or unknown container means "cannot prove death".
     */
    public static function provenDead(array $incarnation): bool
    {
        $bootId = self::readFile('/proc/sys/kernel/random/boot_id');

        if ($bootId !== (string) ($incarnation['boot_id'] ?? '')) {
            // Host rebooted: every prior incarnation is gone.
            return true;
        }

        $pid = (int) ($incarnation['pid'] ?? 0);
        $recordedStart = (string) ($incarnation['process_start_time'] ?? '');

        if ($pid === 0 || $recordedStart === '') {
            return false; // Cannot verify → fail closed.
        }

        $currentStart = self::processStartTime($pid);

        // Either the pid is gone, or it was reused by a different process
        // (different start time) — both prove THIS incarnation is dead.
        return $currentStart !== $recordedStart;
    }

    /** Field 22 of /proc/<pid>/stat; parses past the parenthesized comm. */
    public static function processStartTime(int $pid): string
    {
        $stat = self::readFile("/proc/{$pid}/stat");

        if ($stat === '') {
            return '';
        }

        // comm may contain spaces — everything after the LAST ')' is fixed.
        $afterComm = substr($stat, strrpos($stat, ')') + 2);

        return (string) (explode(' ', $afterComm)[19] ?? '');
    }

    /**
     * The immutable Docker container id: cgroup v2 "0::/" paths carry the
     * 64-hex id at the tail; cgroup v1 paths embed docker-<id>. Falls
     * back to /etc/hostname (compose sets it to the container id short
     * form) — diagnostics, with boot_id still pinning the incarnation.
     */
    public static function containerId(): string
    {
        $mountinfo = self::readFile('/proc/self/mountinfo');

        if ($mountinfo !== '' && preg_match('/docker[\/-]([0-9a-f]{64})/', $mountinfo, $m) === 1) {
            return $m[1];
        }

        $cgroup = self::readFile('/proc/self/cgroup');

        if ($cgroup !== '' && preg_match('/([0-9a-f]{64})/', $cgroup, $m) === 1) {
            return $m[1];
        }

        return self::readFile('/etc/hostname');
    }

    private static function connectionId(): ?int
    {
        try {
            $row = DB::select('select connection_id() as cid');

            return (int) $row[0]->cid;
        } catch (Throwable) {
            return null; // SQLite (tests): liveness reconciliation no-ops.
        }
    }

    private static function readFile(string $path): string
    {
        try {
            return trim((string) @file_get_contents($path));
        } catch (Throwable) {
            return '';
        }
    }
}
