<?php

namespace Modules\Catalog\Search;

/**
 * The exact process-incarnation identity (v12 F1,
 * postgres-migration §3.2): boot_id + container id + pid + process start
 * time — immune to pid reuse and container recreation, verifiable inside
 * the recorded container. Death proof is necessary but never sufficient
 * for a UID-less intent (worker death ≠ remote-task quiescence, v20 F1).
 *
 * `instanceToken()` is the per-process worker identity for admissions,
 * writer leases, and the heartbeat table — stable for the process's
 * lifetime, generated once. The MySQL connection_id()/processlist
 * machinery is gone: reclamation reconciles via heartbeats + this class's
 * /proc death proof (topology-scoped — see SearchMutationGate).
 */
final class OwnerIncarnation
{
    private static ?string $instanceToken = null;

    /** Per-process worker identity: random 32-hex, generated once. */
    public static function instanceToken(): string
    {
        return self::$instanceToken ??= bin2hex(random_bytes(16));
    }

    /** @return array<string, int|string> */
    public static function current(): array
    {
        $bootId = self::readFile('/proc/sys/kernel/random/boot_id');
        $containerId = self::containerId();

        return [
            'instance_token' => self::instanceToken(),
            'boot_id' => substr($bootId, 0, 64),
            'container_id' => substr($containerId, 0, 64),
            // pid 1's start time pins the container incarnation (ticks
            // since boot); empty when unavailable (non-Linux dev).
            'container_started_at' => self::processStartTime(1),
            'pid' => getmypid() ?: 0,
            'process_start_time' => self::processStartTime(getmypid() ?: 0),
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
     *
     * Topology scope (postgres-migration §3.2.1): boot_id is host-global
     * (shared kernel), so a mismatch proves a host reboot killed every
     * prior incarnation — including other containers'. Pid-level checks
     * are only meaningful within the prover's own PID namespace (same
     * container): a sibling container's /proc is unreadable, and a missing
     * /proc entry on the prover's host is NEVER proof about another
     * container/host.
     */
    public static function provenDead(array $incarnation): bool
    {
        $bootId = self::readFile('/proc/sys/kernel/random/boot_id');

        if ($bootId !== (string) ($incarnation['boot_id'] ?? '')) {
            // Host rebooted: every prior incarnation is gone.
            return true;
        }

        // Cross-container (different PID namespace): the pid-level
        // evidence below would inspect the WRONG namespace — only the
        // host-global boot_id check above is valid, and it passed. Death
        // is unprovable here: fail closed.
        $proverContainer = self::containerId();
        if ((string) ($incarnation['container_id'] ?? '') !== $proverContainer) {
            return false;
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

    private static function readFile(string $path): string
    {
        try {
            return trim((string) @file_get_contents($path));
        } catch (\Throwable) {
            return '';
        }
    }
}
