<?php

namespace Modules\Discount\Actions\Admin\Promotion;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Support\Tenancy\AssertsTenantOwnership;
use Modules\Discount\Exceptions\Promotion\UpdateFailedException;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Throwable;

class UpdatePromotionAction
{
    public function __construct(private readonly CatalogGatewayInterface $catalog) {}

    /**
     * @throws UpdateFailedException|Throwable
     */
    /** @param  array<string, mixed>  $data */
    public function handle(string $id, array $data): Promotion
    {
        /** @var Promotion $record */
        $record = Promotion::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            // Payload ids are untrusted: reject anything the active store
            // does not own before updating the promotion or its pivots.
            AssertsTenantOwnership::assertIds(
                $data[PromotionSchema::PRODUCT_IDS] ?? [],
                fn (int $id): bool => $this->catalog->productExists($id),
            );
            AssertsTenantOwnership::assertIds(
                $data[PromotionSchema::CATEGORY_IDS] ?? [],
                fn (int $id): bool => $this->catalog->categoryExists($id),
            );

            $record->fill([
                PromotionSchema::NAME => $data[PromotionSchema::NAME] ?? $record->{PromotionSchema::NAME},
                PromotionSchema::SCOPE => $data[PromotionSchema::SCOPE] ?? $record->{PromotionSchema::SCOPE},
                PromotionSchema::TYPE => $data[PromotionSchema::TYPE] ?? $record->{PromotionSchema::TYPE},
                PromotionSchema::VALUE => $data[PromotionSchema::VALUE] ?? $record->{PromotionSchema::VALUE},
                PromotionSchema::STARTS_AT => $data[PromotionSchema::STARTS_AT] ?? $record->{PromotionSchema::STARTS_AT},
                PromotionSchema::ENDS_AT => $data[PromotionSchema::ENDS_AT] ?? $record->{PromotionSchema::ENDS_AT},
                PromotionSchema::PRIORITY => $data[PromotionSchema::PRIORITY] ?? $record->{PromotionSchema::PRIORITY},
                PromotionSchema::IS_ACTIVE => $data[PromotionSchema::IS_ACTIVE] ?? $record->{PromotionSchema::IS_ACTIVE},
            ])->save();

            // Scope is replaced wholesale — selections always mirror the
            // request, and scope=all actively clears stale pivots
            $record->syncScope(
                $data[PromotionSchema::PRODUCT_IDS] ?? [],
                $data[PromotionSchema::CATEGORY_IDS] ?? [],
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record;
    }
}
