<?php

namespace Modules\Catalog\Search;

/**
 * Outcome of an operator admission override (§3.2.2). Exactly one of:
 *
 *  - released   — the token's open admissions were closed by the operator;
 *  - superseded — the token is known but nothing of its is open any more
 *                 (already released or reclaimed): nothing was touched;
 *  - not-found  — no heartbeat row carries this token: nothing was touched.
 */
final readonly class SearchAdmissionOverrideResult
{
    public function __construct(
        public string $status,
        public int $released,
        public ?object $heartbeat,
    ) {}
}
