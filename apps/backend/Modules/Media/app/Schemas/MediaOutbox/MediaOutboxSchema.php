<?php

namespace Modules\Media\Schemas\MediaOutbox;

use Modules\Media\Schemas\Module;

/**
 * The transactional media-publication outbox (postgres-migration §3.3):
 * the durable record committing a media row and its file publication as
 * one unit. Staged bytes land on a same-filesystem staging prefix; the
 * processor claims entries under a lease (claim token + epoch),
 * hash-verifies staged bytes, publishes to the immutable content-addressed
 * final key, and completes the entry via the claim-conditioned terminal
 * transition in the SAME transaction as the media-row path update — zero
 * affected rows means lost ownership and NO authoritative change.
 */
class MediaOutboxSchema
{
    public const string TABLE = Module::DB_PREFIX.'media_outbox';

    public const string MEDIA_ID = 'media_id';

    public const string EXPECTED_HASH = 'expected_hash';

    public const string STAGING_PATH = 'staging_path';

    public const string FINAL_KEY = 'final_key';

    public const string STATUS = 'status';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_DONE = 'done';

    public const string STATUS_FAILED = 'failed';

    public const string CLAIM_TOKEN = 'claim_token';

    public const string CLAIM_EPOCH = 'claim_epoch';

    public const string CLAIMED_AT = 'claimed_at';

    public const string PUBLISHED_PATH = 'published_path';

    public const string PUBLISHED_AT = 'published_at';

    public const string ATTEMPTS = 'attempts';

    public const string LAST_ERROR = 'last_error';
}
