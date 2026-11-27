<?php

namespace Modules\Discount\Actions\Admin\Promotion;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Support\Tenancy\AssertsTenantOwnership;
use Modules\Discount\Exceptions\Promotion\CreationFailedException;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Throwable;

class CreatePromotionAction
{
    public function __construct(private readonly CatalogGatewayInterface $catalog) {}

    /**
     * @throws CreationFailedException|Throwable
     */
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): Promotion
    {
        try {
            DB::beginTransaction();

            // Payload ids are untrusted: reject anything the active store
            // does not own before creating the promotion or its pivots.
            AssertsTenantOwnership::assertIds(
                $data[PromotionSchema::PRODUCT_IDS] ?? [],
                fn (int $id): bool => $this->catalog->productExists($id),
            );
            AssertsTenantOwnership::assertIds(
                $data[PromotionSchema::CATEGORY_IDS] ?? [],
                fn (int $id): bool => $this->catalog->categoryExists($id),
            );

            $record = Promotion::query()->create([
                PromotionSchema::NAME => $data[PromotionSchema::NAME],
                PromotionSchema::SCOPE => $data[PromotionSchema::SCOPE],
                PromotionSchema::TYPE => $data[PromotionSchema::TYPE],
                PromotionSchema::VALUE => $data[PromotionSchema::VALUE],
                PromotionSchema::STARTS_AT => $data[PromotionSchema::STARTS_AT] ?? null,
                PromotionSchema::ENDS_AT => $data[PromotionSchema::ENDS_AT] ?? null,
                PromotionSchema::PRIORITY => $data[PromotionSchema::PRIORITY] ?? 0,
                PromotionSchema::IS_ACTIVE => $data[PromotionSchema::IS_ACTIVE] ?? true,
            ]);

            // Pivot sync stays inside the transaction: a scope-write failure
            // rolls the whole promotion back instead of leaving a row whose
            // scope no longer matches its selections
            $record->syncScope(
                $data[PromotionSchema::PRODUCT_IDS] ?? [],
                $data[PromotionSchema::CATEGORY_IDS] ?? [],
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record;
    }
}
