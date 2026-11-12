<?php

namespace Modules\Core\Support\Fx;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\Gateways\Fx\FxProviderException;
use Modules\Core\Contracts\Gateways\Fx\FxProviderInterface;
use Modules\Core\Contracts\Gateways\Fx\FxRateSnapshot;

/**
 * openexchangerates.org provider (Part E, v1's only driver).
 *
 * Credential handling (review F5): the API key travels ONLY in the
 * `Authorization: Token` header — never a query parameter — and never
 * appears in exceptions, logs, or status output. Raw provider bodies
 * are never surfaced; failures carry sanitized error codes only
 * (F22: 401 = auth failure, 403 = plan/feature restriction).
 *
 * Parsing (review F16/F2): the response is parsed in two tiers.
 * Structural validation runs on the JSON-decoded document (the decoded
 * float values are DISCARDED — never used as rates); the rate VALUES
 * are then extracted as exact decimal strings from the raw `rates`
 * object slice, so no number round-trips through binary floating
 * point. Document-level failures reject the whole snapshot; individual
 * rate values (e.g. exponent notation) are passed through and skipped
 * later by the formatter.
 *
 * Base currency (review F1): openexchangerates' free plan always
 * returns USD-relative rates and ignores base selection. The provider
 * therefore does NOT request a base — it reports the document's actual
 * base, and the sync engine fails closed when it differs from the
 * store's base currency. A non-USD store base requires a plan whose
 * base selection matches, by contract of this driver.
 */
class OpenExchangeRatesProvider implements FxProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
    ) {}

    public function fetchRates(string $baseCode): FxRateSnapshot
    {
        try {
            $response = Http::timeout((int) config('core.fx.http.timeout', 15))
                ->withHeaders(['Authorization' => 'Token '.$this->apiKey])
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/latest.json');
        } catch (ConnectionException) {
            throw FxProviderException::forCode(FxProviderException::TIMEOUT);
        }

        if ($response->status() === 401) {
            throw FxProviderException::forCode(FxProviderException::AUTH_FAILED);
        }

        if ($response->status() === 403) {
            throw FxProviderException::forCode(FxProviderException::FORBIDDEN);
        }

        if ($response->failed()) {
            throw FxProviderException::forCode(FxProviderException::HTTP_ERROR);
        }

        return $this->parse($response->body());
    }

    /**
     * Parse and validate the raw response document. Public: this is
     * the testable parsing seam — unit tests exercise it against
     * hand-built documents without HTTP.
     *
     * @throws FxProviderException structurally invalid document
     */
    public function parse(string $body): FxRateSnapshot
    {
        $document = json_decode($body, true);

        if (! is_array($document)) {
            throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
        }

        $base = $document['base'] ?? null;
        $timestamp = $document['timestamp'] ?? null;
        $rates = $document['rates'] ?? null;

        if (! is_string($base) || ! preg_match('/^[A-Z]{3}$/', $base)) {
            throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
        }

        if (! is_int($timestamp) && ! is_numeric($timestamp)) {
            throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
        }

        if (! is_array($rates) || $rates === []) {
            throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
        }

        // Structure tier: every key is a clean alpha-3 code and every
        // value a JSON number. Nested arrays / string values make the
        // WHOLE document invalid (F16).
        foreach ($rates as $code => $value) {
            if (! is_string($code) || ! preg_match('/^[A-Z]{3}$/', $code)) {
                throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
            }

            if (! is_int($value) && ! is_float($value)) {
                throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
            }
        }

        // Value tier: extract the exact numeric literals as strings from
        // the raw `rates` slice. Duplicate keys or extraction drift
        // (match count ≠ decoded count) ⇒ ambiguous document ⇒ reject.
        $rawRates = $this->extractRawRates($body);

        if (count($rawRates) !== count($rates)) {
            throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
        }

        return new FxRateSnapshot(
            baseCode: $base,
            timestamp: (int) $timestamp,
            rates: $rawRates,
        );
    }

    /**
     * Extract `"CODE": <number literal>` pairs from the raw `rates`
     * object only — never from unrelated response fields.
     *
     * @return array<string, string> code => raw numeric token
     */
    protected function extractRawRates(string $body): array
    {
        $slice = $this->ratesSlice($body);

        if ($slice === null) {
            throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
        }

        preg_match_all('/"([A-Z]{3})"\s*:\s*(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/', $slice, $matches, PREG_SET_ORDER);

        $rates = [];

        foreach ($matches as $match) {
            // A repeated code inside the slice is an ambiguous document.
            if (array_key_exists($match[1], $rates)) {
                throw FxProviderException::forCode(FxProviderException::INVALID_DOCUMENT);
            }

            $rates[$match[1]] = $match[2];
        }

        return $rates;
    }

    /**
     * Isolate the top-level `rates` object from the raw body by brace
     * tracking, so numeric-looking values elsewhere (disclaimer ids,
     * timestamps) can never leak into the rate set.
     */
    protected function ratesSlice(string $body): ?string
    {
        $keyPosition = strpos($body, '"rates"');

        if ($keyPosition === false) {
            return null;
        }

        $open = strpos($body, '{', $keyPosition);

        if ($open === false) {
            return null;
        }

        $depth = 0;

        for ($i = $open, $length = strlen($body); $i < $length; $i++) {
            if ($body[$i] === '{') {
                $depth++;

                continue;
            }

            if ($body[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($body, $open, $i - $open + 1);
                }
            }
        }

        return null;
    }
}
