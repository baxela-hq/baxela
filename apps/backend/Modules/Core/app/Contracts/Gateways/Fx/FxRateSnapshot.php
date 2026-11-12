<?php

namespace Modules\Core\Contracts\Gateways\Fx;

/**
 * One immutable provider response (review F6): the rates plus the
 * metadata needed to distinguish "when Baxela synchronized" from
 * "when the provider published these rates", and to detect a provider
 * that ignored the requested base (F1 — fail closed on mismatch).
 *
 * Rates are EXACT decimal strings keyed by ISO alpha-3 code. Values
 * that failed financial validation are absent — the sync engine skips
 * them (F16: document-level vs per-rate failure separation).
 *
 * @param  array<string, string>  $rates
 */
final readonly class FxRateSnapshot
{
    /**
     * @param  array<string, string>  $rates
     */
    public function __construct(
        public string $baseCode,
        public int $timestamp,
        public array $rates,
    ) {}
}
