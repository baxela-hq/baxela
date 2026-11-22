<?php

namespace Modules\Catalog\Schemas\Search;

use Modules\Catalog\Schemas\Module;

/**
 * Column/table names for the Meilisearch coordination layer (alpha.3):
 * the mutation gate, per-process admissions, per-product/generation
 * writer leases, the durable operation journal, and the per-generation
 * replay-application record. The journal (operations) rows are written
 * transactionally with every product/variant mutation — see
 * Modules/Catalog/docs/search-indexing.md for the protocol contract.
 */
final class SearchCoordinationSchema
{
    public const string GATE_TABLE = Module::DB_PREFIX.'search_gate';

    public const string ADMISSIONS_TABLE = Module::DB_PREFIX.'search_admissions';

    public const string LEASES_TABLE = Module::DB_PREFIX.'search_writer_leases';

    public const string OPERATIONS_TABLE = Module::DB_PREFIX.'search_operations';

    public const string REPLAY_APPLIED_TABLE = Module::DB_PREFIX.'search_replay_applied';

    public const string CURSORS_TABLE = Module::DB_PREFIX.'search_product_cursors';

    public const string RETIRED_TABLE = Module::DB_PREFIX.'search_retired_products';

    public const string WORKER_HEARTBEATS_TABLE = Module::DB_PREFIX.'search_worker_heartbeats';

    // gate
    public const string STATE = 'state';

    public const string GENERATION = 'generation';

    public const string SERVING_INDEX_UID = 'serving_index_uid';

    public const string NEXT_GENERATION = 'next_generation';

    public const string OWNER = 'owner';

    public const string HEARTBEAT_AT = 'heartbeat_at';

    public const string STATE_OPEN = 'open';

    public const string STATE_CLOSED = 'closed';

    // admissions
    public const string BOOT_ID = 'boot_id';

    public const string CONTAINER_ID = 'container_id';

    public const string CONTAINER_STARTED_AT = 'container_started_at';

    public const string PID = 'pid';

    public const string PROCESS_START_TIME = 'process_start_time';

    /** Per-process worker identity (§3.2): replaces db_connection_id/processlist. */
    public const string INSTANCE_TOKEN = 'instance_token';

    public const string ACQUIRED_AT = 'acquired_at';

    public const string RELEASED_AT = 'released_at';

    // writer leases
    public const string PRODUCT_ID = 'product_id';

    public const string LEASE_UNTIL = 'lease_until';

    // operations (the durable journal; intents are committed before the
    // remote call, task UIDs persisted after acceptance)
    public const string KIND = 'kind';

    public const string KIND_DOCUMENT = 'document';

    public const string TARGET_INDEX_UID = 'target_index_uid';

    public const string INTENT_UID = 'intent_uid';

    public const string TASK_UID = 'task_uid';

    /** Managed uniqueness key: product_id while unresolved, NULL once resolved/superseded. */
    public const string DOC_KEY = 'doc_key';

    public const string SUBMISSION_STARTED_AT = 'submission_started_at';

    public const string TASK_STATUS = 'task_status';

    public const string CREATED_AT = 'created_at';

    public const string RESOLVED_AT = 'resolved_at';

    public const string SUPERSEDED_AT = 'superseded_at';

    public const string SUPERSEDED_BY_GENERATION = 'superseded_by_generation';

    // replay applied
    public const string JOURNAL_ENTRY_ID = 'journal_entry_id';

    public const string COVERED_BY_OPERATION_ID = 'covered_by_operation_id';

    public const string APPLIED_AT = 'applied_at';

    // per-product cursors (§3.4.1): the commit-ordered journal authority
    public const string CURSOR = 'cursor';

    public const string LAST_OPERATION_ID = 'last_operation_id';

    // retired product IDs (§3.4.1a): authoritative gating registry
    public const string RETIRED_AT = 'retired_at';

    public const string RETIRED_BY = 'retired_by';

    // worker heartbeats (§3.2.2): liveness for admissions + writer leases
    public const string INSTANCE_BOOT_ID = 'boot_id';

    public const string WORKER_OWNER = 'owner';
}
