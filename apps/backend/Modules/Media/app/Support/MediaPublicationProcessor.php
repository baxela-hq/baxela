<?php

namespace Modules\Media\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Support\Tenancy\TenantQueryScope;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Schemas\MediaOutbox\MediaOutboxSchema;
use Throwable;

/**
 * The media outbox processor (postgres-migration §3.3.2): publishes staged
 * bytes to their immutable content-addressed final key and completes the
 * outbox entry through the fenced terminal transition.
 *
 * The atomic boundary, in order:
 *
 *  1. Verify the staged bytes' sha256 against the expected hash BEFORE any
 *     publication (mismatch ⇒ no publish, no DB authority change).
 *  2. Publish to the immutable final key — content-addressed, so a retry
 *     or a racing duplicate writing identical bytes is a no-op by
 *     construction (different content hashes to a different key).
 *  3. ONE transaction: the claim-conditioned terminal UPDATE
 *     (`WHERE id AND claim_token AND claim_epoch AND status = processing`)
 *     plus the media-row path update — the affected-row count is checked
 *     BEFORE the media update; zero rows = lost ownership = the
 *     transaction aborts with NO authoritative change at all.
 *
 * Invariant: only the current claim token and epoch can make a published
 * object authoritative in the database.
 *
 * Recovery: crashed processors leave `processing` entries whose claim is
 * stale; those are re-claimable after the claim-grace window (the token
 * guard makes double-processing harmless — immutable keys + conditional
 * completion). Failures back off per attempt and stay sweep-eligible.
 */
final class MediaPublicationProcessor
{
    public function __construct(
        private readonly string $disk = '',
        private readonly int $claimGraceSeconds = 300,
        private readonly int $batch = 50,
    ) {}

    /** @return array{int, int} [completed, deferred] */
    public function process(?int $limit = null): array
    {
        $disk = $this->disk !== '' ? $this->disk : (string) config('filesystems.default');
        $limit ??= $this->batch;
        $completed = 0;
        $deferred = 0;

        $entryIds = $this->claimableEntryIds($limit);

        foreach ($entryIds as $entryId) {
            $entry = TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)->where('id', $entryId))->first();

            if ($entry === null) {
                continue;
            }

            try {
                $this->publish((object) (array) $entry, $disk)
                    ? $completed++
                    : $deferred++;
            } catch (Throwable $e) {
                $deferred++;
                $this->recordFailure($entryId, $e);
            }
        }

        return [$completed, $deferred];
    }

    /**
     * The fenced publication of one entry (§3.3.2). True on completion.
     */
    private function publish(object $entry, string $disk): bool
    {
        $token = (string) Str::uuid();

        if (! $this->claim($entry->id, $token)) {
            return false; // Someone else holds a live claim.
        }

        // Re-read post-claim: the entry (with the fresh claim fields).
        $claimed = TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)->where('id', $entry->id))->first();

        if ($claimed === null || $claimed->{MediaOutboxSchema::STATUS} !== MediaOutboxSchema::STATUS_PROCESSING) {
            return false;
        }

        $staging = (string) $claimed->{MediaOutboxSchema::STAGING_PATH};
        $finalKey = (string) $claimed->{MediaOutboxSchema::FINAL_KEY};

        // 1) Hash-verify the staged bytes BEFORE publication.
        $contents = Storage::disk($disk)->get($staging);

        if ($contents === null
            || hash('sha256', $contents) !== (string) $claimed->{MediaOutboxSchema::EXPECTED_HASH}) {
            $this->markFailed((int) $claimed->id, 'staging_hash_mismatch');

            return false;
        }

        // 2) Publish to the immutable content-addressed key (idempotent —
        // identical bytes overwrite identically; the local adapter's put
        // after staging is the same-filesystem publication, remote
        // adapters realize copy → verify → delete-staging semantics).
        Storage::disk($disk)->put($finalKey, $contents);

        // 3) The fenced terminal transition — ONE transaction with the
        // media-row path update; zero affected rows = lost ownership.
        DB::transaction(function () use ($claimed, $token, $finalKey): void {
            $updated = TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)
                ->where('id', $claimed->id)
                ->where(MediaOutboxSchema::CLAIM_TOKEN, $token)
                ->where(MediaOutboxSchema::CLAIM_EPOCH, $claimed->{MediaOutboxSchema::CLAIM_EPOCH})
                ->where(MediaOutboxSchema::STATUS, MediaOutboxSchema::STATUS_PROCESSING))
                ->update([
                    MediaOutboxSchema::STATUS => MediaOutboxSchema::STATUS_DONE,
                    MediaOutboxSchema::PUBLISHED_PATH => $finalKey,
                    MediaOutboxSchema::PUBLISHED_AT => now(),
                    MediaOutboxSchema::LAST_ERROR => null,
                ]);

            if ($updated === 0) {
                // Lost ownership: no authoritative change at all — the
                // transaction (including the media update below) aborts.
                throw new \RuntimeException('media_publication_claim_lost');
            }

            TenantQueryScope::apply(DB::table(MediaSchema::TABLE)
                ->where('id', $claimed->{MediaOutboxSchema::MEDIA_ID}))
                ->update([MediaSchema::PATH => $finalKey]);
        });

        // Best-effort staging cleanup (post-commit; never authoritative).
        try {
            Storage::disk($disk)->delete($staging);
        } catch (Throwable) {
            // The sweep catches leftovers.
        }

        return true;
    }

    /**
     * Claim a pending entry, or a stale `processing` one past its claim
     * grace (crashed processor) — the epoch advance is what fences the
     * previous claimer out of the terminal transition.
     */
    private function claim(int $entryId, string $token): bool
    {
        $staleCutoff = now()->subSeconds($this->claimGraceSeconds);

        $claimed = TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)
            ->where('id', $entryId)
            ->where(fn ($q) => $q
                ->where(MediaOutboxSchema::STATUS, MediaOutboxSchema::STATUS_PENDING)
                ->orWhere(fn ($q2) => $q2
                    ->where(MediaOutboxSchema::STATUS, MediaOutboxSchema::STATUS_PROCESSING)
                    ->where(MediaOutboxSchema::CLAIMED_AT, '<', $staleCutoff))))
            ->update([
                MediaOutboxSchema::STATUS => MediaOutboxSchema::STATUS_PROCESSING,
                MediaOutboxSchema::CLAIM_TOKEN => $token,
                MediaOutboxSchema::CLAIM_EPOCH => DB::raw(MediaOutboxSchema::CLAIM_EPOCH.' + 1'),
                MediaOutboxSchema::CLAIMED_AT => now(),
                MediaOutboxSchema::ATTEMPTS => DB::raw(MediaOutboxSchema::ATTEMPTS.' + 1'),
            ]);

        return $claimed === 1;
    }

    /** @return list<int> */
    private function claimableEntryIds(int $limit): array
    {
        $staleCutoff = now()->subSeconds($this->claimGraceSeconds);

        return TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)
            ->where(fn ($q) => $q
                ->where(MediaOutboxSchema::STATUS, MediaOutboxSchema::STATUS_PENDING)
                ->orWhere(fn ($q2) => $q2
                    ->where(MediaOutboxSchema::STATUS, MediaOutboxSchema::STATUS_PROCESSING)
                    ->where(MediaOutboxSchema::CLAIMED_AT, '<', $staleCutoff)))
            ->orderBy('id')
            ->limit($limit))
            ->pluck('id')
            ->all();
    }

    /** Terminal failure (hash mismatch): staging is kept for review. */
    private function markFailed(int $entryId, string $reason): void
    {
        TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)
            ->where('id', $entryId))
            ->update([
                MediaOutboxSchema::STATUS => MediaOutboxSchema::STATUS_FAILED,
                MediaOutboxSchema::LAST_ERROR => $reason,
            ]);
    }

    private function recordFailure(int $entryId, Throwable $e): void
    {
        TenantQueryScope::apply(DB::table(MediaOutboxSchema::TABLE)
            ->where('id', $entryId)
            ->whereNot(MediaOutboxSchema::STATUS, MediaOutboxSchema::STATUS_DONE))
            ->update([
                MediaOutboxSchema::STATUS => MediaOutboxSchema::STATUS_PENDING,
                MediaOutboxSchema::LAST_ERROR => mb_substr($e->getMessage(), 0, 1024),
            ]);
    }
}
