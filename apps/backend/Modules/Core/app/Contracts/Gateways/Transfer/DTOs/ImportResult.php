<?php

namespace Modules\Core\Contracts\Gateways\Transfer\DTOs;

/**
 * Per-section import outcome. Importers count as they replay, so the
 * recorder is mutable by design; the Transfer engine rolls the per-section
 * results up into the persisted run summary.
 */
final class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    public static function empty(): self
    {
        return new self;
    }

    public function recordCreated(): void
    {
        $this->created++;
    }

    public function recordUpdated(): void
    {
        $this->updated++;
    }

    public function recordSkipped(): void
    {
        $this->skipped++;
    }

    public function recordFailed(string $error): void
    {
        $this->failed++;
        $this->errors[] = $error;
    }

    public function warn(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    /** @return array{created: int, updated: int, skipped: int, failed: int} */
    public function counts(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
        ];
    }

    /** Rows that did not fail — drives the run's completed/failed status. */
    public function touchedCount(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }
}
