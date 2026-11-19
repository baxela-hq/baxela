<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

return new class extends Migration
{
    /**
     * Coordination state for the Meilisearch integration (alpha.3):
     * the mutation gate, per-process admissions, per-product/generation
     * writer leases, the durable operation journal, and per-generation
     * replay-application records. See Modules/Catalog/docs/
     * search-indexing.md for the protocol these enforce.
     */
    public function up(): void
    {
        Schema::create(SearchCoordinationSchema::GATE_TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(SearchCoordinationSchema::STATE)->default(SearchCoordinationSchema::STATE_OPEN);
            $table->unsignedBigInteger(SearchCoordinationSchema::GENERATION)->default(1);
            $table->string(SearchCoordinationSchema::SERVING_INDEX_UID)->default('catalog_products_g1');
            // Monotonic allocator: the next generation number to hand out.
            // Failed/abandoned builds consume theirs permanently — never reused.
            $table->unsignedBigInteger(SearchCoordinationSchema::NEXT_GENERATION)->default(2);
            $table->string(SearchCoordinationSchema::OWNER)->nullable();
            $table->timestamp(SearchCoordinationSchema::HEARTBEAT_AT)->nullable();
            $table->timestamps();
        });

        Schema::create(SearchCoordinationSchema::ADMISSIONS_TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(SearchCoordinationSchema::OWNER);
            // Process-incarnation identity (v12): proves the EXACT process,
            // immune to pid reuse and container recreation.
            $table->string(SearchCoordinationSchema::BOOT_ID);
            $table->string(SearchCoordinationSchema::CONTAINER_ID);
            $table->string(SearchCoordinationSchema::CONTAINER_STARTED_AT);
            $table->unsignedBigInteger(SearchCoordinationSchema::PID);
            $table->unsignedBigInteger(SearchCoordinationSchema::PROCESS_START_TIME);
            // The process's own MySQL connection id — an admission whose
            // connection no longer exists was rolled back server-side, so
            // reclaiming it can never race a live transaction.
            $table->unsignedBigInteger(SearchCoordinationSchema::DB_CONNECTION_ID)->nullable();
            $table->unsignedBigInteger(SearchCoordinationSchema::GENERATION);
            $table->timestamp(SearchCoordinationSchema::ACQUIRED_AT);
            $table->timestamp(SearchCoordinationSchema::RELEASED_AT)->nullable();
            $table->index([SearchCoordinationSchema::RELEASED_AT, SearchCoordinationSchema::ACQUIRED_AT]);
            $table->timestamps();
        });

        Schema::create(SearchCoordinationSchema::LEASES_TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(SearchCoordinationSchema::PRODUCT_ID);
            // Generation-scoped: an old-generation writer's lease can never
            // block a newer generation's writer (v17).
            $table->unsignedBigInteger(SearchCoordinationSchema::GENERATION);
            $table->string(SearchCoordinationSchema::OWNER);
            $table->timestamp(SearchCoordinationSchema::LEASE_UNTIL);
            $table->timestamp(SearchCoordinationSchema::ACQUIRED_AT);

            $table->unique([
                SearchCoordinationSchema::PRODUCT_ID,
                SearchCoordinationSchema::GENERATION,
            ], 'catalog_swl_product_gen_unique');
        });

        Schema::create(SearchCoordinationSchema::OPERATIONS_TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(SearchCoordinationSchema::KIND)->default(SearchCoordinationSchema::KIND_DOCUMENT);
            $table->unsignedBigInteger(SearchCoordinationSchema::PRODUCT_ID)->index();
            $table->unsignedBigInteger(SearchCoordinationSchema::GENERATION);
            $table->string(SearchCoordinationSchema::TARGET_INDEX_UID);
            // Durable before the remote call (two-phase submission): the
            // intent row is what recovery finds when the process dies
            // between Meili acceptance and task-UID persistence.
            $table->string(SearchCoordinationSchema::INTENT_UID)->unique();
            $table->string(SearchCoordinationSchema::TASK_UID)->nullable()->index();
            // Managed uniqueness key: product_id while unresolved, NULL once
            // resolved/superseded — enforces "at most one unresolved document
            // operation per product" at the schema level, and its release is
            // the supersession transition itself (never a fake completion).
            $table->string(SearchCoordinationSchema::DOC_KEY)->nullable()->unique();
            // Owner-checked CAS authority for the in-flight submission.
            $table->string(SearchCoordinationSchema::OWNER)->nullable();
            $table->timestamp(SearchCoordinationSchema::SUBMISSION_STARTED_AT)->nullable();
            $table->string(SearchCoordinationSchema::TASK_STATUS)->nullable();
            // Terminal REMOTE state (processed/failed/canceled) — recorded
            // even when failed; a failed task never acknowledges its journal.
            $table->timestamp(SearchCoordinationSchema::RESOLVED_AT)->nullable();
            $table->timestamp(SearchCoordinationSchema::SUPERSEDED_AT)->nullable();
            $table->unsignedBigInteger(SearchCoordinationSchema::SUPERSEDED_BY_GENERATION)->nullable();
            $table->timestamp(SearchCoordinationSchema::CREATED_AT)->useCurrent();

            $table->index([SearchCoordinationSchema::SUPERSEDED_AT, SearchCoordinationSchema::RESOLVED_AT]);
            $table->index([SearchCoordinationSchema::TARGET_INDEX_UID, SearchCoordinationSchema::GENERATION]);
        });

        Schema::create(SearchCoordinationSchema::REPLAY_APPLIED_TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(SearchCoordinationSchema::GENERATION);
            $table->unsignedBigInteger(SearchCoordinationSchema::JOURNAL_ENTRY_ID);
            $table->unsignedBigInteger(SearchCoordinationSchema::PRODUCT_ID)->index();
            $table->unsignedBigInteger(SearchCoordinationSchema::COVERED_BY_OPERATION_ID)->nullable();
            $table->timestamp(SearchCoordinationSchema::APPLIED_AT);

            // The ONLY completeness record for a generation's replay — the
            // scan cursor is a position, never proof of application.
            $table->unique([
                SearchCoordinationSchema::GENERATION,
                SearchCoordinationSchema::JOURNAL_ENTRY_ID,
            ], 'catalog_sra_gen_entry_unique');
        });

        // Seed the gate: generation 1 (pre-search state) with its uid.
        DB::table(SearchCoordinationSchema::GATE_TABLE)->insert([
            SearchCoordinationSchema::STATE => SearchCoordinationSchema::STATE_OPEN,
            SearchCoordinationSchema::GENERATION => 1,
            SearchCoordinationSchema::SERVING_INDEX_UID => 'catalog_products_g1',
            SearchCoordinationSchema::NEXT_GENERATION => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(SearchCoordinationSchema::REPLAY_APPLIED_TABLE);
        Schema::dropIfExists(SearchCoordinationSchema::OPERATIONS_TABLE);
        Schema::dropIfExists(SearchCoordinationSchema::LEASES_TABLE);
        Schema::dropIfExists(SearchCoordinationSchema::ADMISSIONS_TABLE);
        Schema::dropIfExists(SearchCoordinationSchema::GATE_TABLE);
    }
};
