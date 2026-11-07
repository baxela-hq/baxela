<?php

namespace Modules\Order\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Order\Models\DocumentSequence;
use Modules\Order\Schemas\Document\DocumentSequenceSchema;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;

/**
 * Atomic, never-reused document numbers (D3): `{PREFIX}-{YEAR}-{6-digit}`
 * per (type, year), allocated inside the CALLER's transaction — the number
 * and its document are born in the same commit. First allocation for a
 * (type, year) creates the sequence row under contention: the losing
 * transaction's insert blocks on the winner's lock, fails with a unique
 * violation, then locks the now-committed row. Later allocations take the
 * plain row lock. Numbers are never reused; voided documents keep theirs.
 */
class DocumentNumberService
{
    /**
     * @return string e.g. INV-2026-000001
     */
    public function next(DocumentSequenceTypeEnum $type): string
    {
        $year = StoreTimezone::year();

        $sequence = DocumentSequence::query()
            ->where(DocumentSequenceSchema::TYPE, $type)
            ->where(DocumentSequenceSchema::YEAR, $year)
            ->lockForUpdate()
            ->first();

        if (is_null($sequence)) {
            try {
                $sequence = DocumentSequence::query()->create([
                    DocumentSequenceSchema::TYPE => $type,
                    DocumentSequenceSchema::YEAR => $year,
                    DocumentSequenceSchema::LAST_NUMBER => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent first allocation committed its row between
                // our empty read and our insert — serialize on it now.
                $sequence = DocumentSequence::query()
                    ->where(DocumentSequenceSchema::TYPE, $type)
                    ->where(DocumentSequenceSchema::YEAR, $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $next = $sequence->{DocumentSequenceSchema::LAST_NUMBER} + 1;
        $sequence->{DocumentSequenceSchema::LAST_NUMBER} = $next;
        $sequence->save();

        return sprintf('%s-%d-%06d', (string) config("order.documents.prefixes.{$type->value}"), $year, $next);
    }
}
