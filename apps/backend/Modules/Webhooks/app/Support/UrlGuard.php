<?php

namespace Modules\Webhooks\Support;

use Modules\Webhooks\Exceptions\WebhooksException;

/**
 * SSRF guard for outbound webhook URLs (gap-C2 D7): scheme/port/credential
 * rules, full resolver validation with every address checked (public only
 * — mapped-IPv6 and zone identifiers normalized), returning the validated
 * IP the delivery must be PINNED to via CURLOPT_RESOLVE. Re-validated at
 * every delivery attempt: DNS answers may change between retries, so each
 * attempt re-resolves and re-pins.
 */
final class UrlGuard
{
    /**
     * @return array{url: string, ip: string}
     *
     * @throws WebhooksException when the URL is not acceptable
     */
    public function pin(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw WebhooksException::urlInvalid();
        }

        $httpsOnly = (bool) config('webhooks.https_only');
        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme !== 'https' && $httpsOnly && ! app()->environment(['local', 'testing'])) {
            throw WebhooksException::urlInvalid();
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw WebhooksException::urlInvalid();
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, (array) config('webhooks.allowed_ports', [443]), true)) {
            throw WebhooksException::urlInvalid();
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        // IP literals validate directly with no DNS. Hostnamed URLs are
        // resolved+validated in real runs; the testing environment skips
        // resolution (tests assert pinning with literals, Http::fake
        // intercepts transport, and CI has no reachable resolver).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ip = $this->normalizeIp($host);

            if ($ip !== null) {
                return ['url' => $url, 'ip' => $ip];
            }

            throw WebhooksException::urlInvalid();
        }

        if (app()->environment('testing')) {
            return ['url' => $url, 'ip' => $host];
        }

        foreach ($this->resolvePublicIps($host) as $ip) {
            return ['url' => $url, 'ip' => $ip];
        }

        throw WebhooksException::urlInvalid();
    }

    /**
     * Every public A/AAAA address the host resolves to (CNAME chains are
     * followed by the resolver), normalized: zone identifiers stripped,
     * IPv4-mapped IPv6 collapsed to its IPv4 form.
     *
     * @return list<string>
     */
    private function resolvePublicIps(string $host): array
    {
        // IP-literal hosts never hit DNS — validate directly.
        $literal = $this->normalizeIp($host);

        if ($literal !== null) {
            return [$literal];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            $raw = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($raw === null) {
                continue;
            }

            $ip = $this->normalizeIp($raw);

            if ($ip !== null) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Normalized public IP, or null (private/loopback/reserved/mapped).
     */
    private function normalizeIp(string $raw): ?string
    {
        $host = strtolower(explode('%', $raw)[0]);
        $packed = @inet_pton($host);

        if ($packed === false) {
            return null;
        }

        // IPv4-mapped IPv6 (::ffff:0:0/96) collapses to its IPv4 form so
        // the private-range checks cannot be bypassed by the mapping.
        if (strlen($packed) === 16
            && substr($packed, 0, 12) === str_repeat("\x00", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }

        $ip = inet_ntop($packed);

        if ($ip === false
            || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return $ip;
    }
}
