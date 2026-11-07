<?php

namespace Modules\Core\Support\FailedJob;

use Modules\Core\Schemas\FailedJob\FailedJobSchema;

/**
 * The single site for turning the raw failed_jobs payload/exception columns
 * into admin-displayable values (gap-B3 D3).
 *
 * Rules, in order of hardness:
 *  - the serialized job command (`data.command`) is NEVER displayed or
 *    unserialized — it is replaced by a placeholder;
 *  - values under secret-ish keys (config `core.queue.failed_jobs.redacted_payload_keys`,
 *    case-insensitive substring match on the key) are masked;
 *  - the payload JSON envelope's `displayName` is the safe source for the job
 *    name; when absent, only the class name is extracted from the serialized
 *    command header — never the blob itself;
 *  - an unparseable payload is reported, not guessed at.
 *
 * Everything it returns is plain JSON-safe data; rendering as non-executable
 * text is the admin panel's contract.
 */
class FailedJobPayloadPresenter
{
    /** Placeholder replacing the serialized `data.command` blob. */
    public const string COMMAND_PLACEHOLDER = '[serialized job command — not displayed]';

    /** Placeholder for denylist-masked values. */
    public const string REDACTED = '[redacted]';

    /** Value returned when the payload column is not decodable JSON. */
    public const string UNPARSEABLE = '[unparseable payload — not displayed]';

    /**
     * The job's display name: the envelope's `displayName` when present,
     * otherwise the class name extracted from the serialized command header.
     */
    public function displayName(string $payload): ?string
    {
        $decoded = json_decode($payload, true);

        if (is_array($decoded) && is_string($decoded['displayName'] ?? null) && $decoded['displayName'] !== '') {
            return $decoded['displayName'];
        }

        // Fallback: the class name is the first string in the serialized
        // object header (O:<len>:"<class>") — the blob itself stays hidden.
        $command = is_array($decoded) ? ($decoded['data']['command'] ?? null) : null;

        if (is_string($command) && preg_match('/^O:\d+:"([^"]+)"/', $command, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Exception excerpt for list rows: server-side truncation to the
     * configured length so list payloads stay small (gap-B3 D2).
     */
    public function exceptionExcerpt(string $exception): string
    {
        $length = max(1, (int) config('core.queue.failed_jobs.exception_excerpt_length', FailedJobSchema::EXCERPT_LENGTH));

        return mb_substr(trim($exception), 0, $length);
    }

    /**
     * The payload for the show endpoint: decoded JSON with the serialized
     * command replaced and secret-ish keys masked. Unparseable payloads are
     * reported conservatively (gap-B3 D3).
     */
    /**
     * @return array<string, mixed>|string
     */
    public function present(string $payload): array|string
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return self::UNPARSEABLE;
        }

        return $this->redact($decoded);
    }

    private function redact(mixed $value, string $path = ''): mixed
    {
        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $child) {
                $childPath = $path === '' ? (string) $key : "{$path}.{$key}";

                if ($childPath === 'data.command') {
                    $result[$key] = self::COMMAND_PLACEHOLDER;

                    continue;
                }

                if ($this->isSecretKey((string) $key)) {
                    $result[$key] = self::REDACTED;

                    continue;
                }

                $result[$key] = $this->redact($child, $childPath);
            }

            return $result;
        }

        return $value;
    }

    private function isSecretKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach ($this->denylist() as $term) {
            if (str_contains($needle, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function denylist(): array
    {
        $configured = config('core.queue.failed_jobs.redacted_payload_keys');

        return is_array($configured) && $configured !== []
            ? array_values(array_map(strtolower(...), $configured))
            : ['password', 'secret', 'token', 'authorization'];
    }
}
