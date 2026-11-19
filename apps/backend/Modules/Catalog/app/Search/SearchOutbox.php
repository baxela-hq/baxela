<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

/**
 * The durable operation journal (two-phase submission, v9 F1): called
 * INSIDE the 5 audited mutation sites' transactions, so a committed
 * product/variant change always has its journal row — even if the
 * process dies before any queue runs. The intent row (task_uid NULL) is
 * what the writer/recovery resolves; the ambiguous case (submission may
 * have happened, UID never persisted) resolves only by the owner, the
 * operator tool with incarnation proof, or generation-bump supersession.
 *
 * A new entry for a product with a still-unresolved operation SUPERSEDES
 * the old one — the fresh-read semantics make the latest entry cover the
 * earlier one (the coalescing rule), so exactly one unresolved operation
 * per product exists, enforced by the doc_key unique key.
 *
 * This call must run inside the caller's transaction — it opens none.
 */
final class SearchOutbox
{
    public function __construct(private readonly SearchGenerationGuard $guard) {}

    public function record(int $productId): int
    {
        $state = $this->guard->servingState();

        DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->whereNull(SearchCoordinationSchema::RESOLVED_AT)
            ->whereNull(SearchCoordinationSchema::SUPERSEDED_AT)
            ->where(SearchCoordinationSchema::GENERATION, $state['generation'])
            ->update([
                SearchCoordinationSchema::SUPERSEDED_AT => now(),
                SearchCoordinationSchema::DOC_KEY => null,
            ]);

        return DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)->insertGetId([
            SearchCoordinationSchema::KIND => SearchCoordinationSchema::KIND_DOCUMENT,
            SearchCoordinationSchema::PRODUCT_ID => $productId,
            SearchCoordinationSchema::GENERATION => $state['generation'],
            SearchCoordinationSchema::TARGET_INDEX_UID => $state['uid'],
            SearchCoordinationSchema::INTENT_UID => bin2hex(random_bytes(16)),
            // Managed uniqueness key: product_id while unresolved — the
            // schema enforces one unresolved operation per product.
            SearchCoordinationSchema::DOC_KEY => (string) $productId,
            'created_at' => now(),
        ]);
    }
}
