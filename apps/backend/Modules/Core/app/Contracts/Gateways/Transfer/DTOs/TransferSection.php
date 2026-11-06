<?php

namespace Modules\Core\Contracts\Gateways\Transfer\DTOs;

/**
 * One named group of rows a module exposes to the Transfer module —
 * usually one entity per section (parents before children). The flags
 * let a module ship export-only data (e.g. transactional records) or
 * import-only groups without special cases in the engine.
 */
final class TransferSection
{
    public function __construct(
        public readonly string $key,
        public readonly bool $exportable = true,
        public readonly bool $importable = true,
    ) {}

    public static function full(string $key): self
    {
        return new self($key);
    }

    public static function exportOnly(string $key): self
    {
        return new self($key, importable: false);
    }

    public static function importOnly(string $key): self
    {
        return new self($key, exportable: false);
    }
}
