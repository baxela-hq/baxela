<?php

namespace Modules\Discount\Transfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Support\Transfer\CrossModuleResolver;
use Modules\Core\Support\Transfer\RecordsTransferRemaps;
use Modules\Core\Support\Transfer\TransferRunContext;
use Modules\Discount\Actions\Admin\Coupon\CreateCouponAction;
use Modules\Discount\Actions\Admin\Coupon\UpdateCouponAction;
use Modules\Discount\Actions\Admin\Promotion\CreatePromotionAction;
use Modules\Discount\Actions\Admin\Promotion\UpdatePromotionAction;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;
use Modules\Discount\Schemas\Module;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;
use Throwable;

/**
 * Discount module's implementation of the data-transfer standard.
 *
 * Coupons and promotions are configuration: matched by natural key
 * (uppercase coupon code, promotion name) and replayed through the admin
 * Actions. Promotions carry their catalog selections as source ids, and
 * the ids are validated through the Catalog gateway exactly like the
 * admin request does — an export replayed into a different catalog
 * fails those rows instead of pointing at the wrong products.
 * Redemptions are history: export-only, with the coupon carried as code.
 */
class DiscountTransfer implements ModuleTransferInterface
{
    use RecordsTransferRemaps;

    public const string SECTION_COUPONS = 'coupons';

    public const string SECTION_PROMOTIONS = 'promotions';

    public const string SECTION_REDEMPTIONS = 'redemptions';

    public function __construct(
        protected CreateCouponAction $createCoupon,
        protected UpdateCouponAction $updateCoupon,
        protected CreatePromotionAction $createPromotion,
        protected UpdatePromotionAction $updatePromotion,
        protected CatalogGatewayInterface $catalogGateway,
        protected CrossModuleResolver $catalogResolver,
        protected DiscountGatewayInterface $discountGateway,
    ) {
        $this->initTransferRecording();
    }

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_COUPONS),
            TransferSection::full(self::SECTION_PROMOTIONS),
            // Order-linked history: importable, but rows referencing orders
            // that don't exist yet (pass 1 of a two-pass restore) skip with
            // an expected operational warning, never a failure.
            TransferSection::full(self::SECTION_REDEMPTIONS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_COUPONS => $this->exportCoupons(),
            self::SECTION_PROMOTIONS => $this->exportPromotions(),
            self::SECTION_REDEMPTIONS => $this->exportRedemptions(),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_COUPONS => $this->importCoupons($rows, $options),
            self::SECTION_PROMOTIONS => $this->importPromotions($rows, $options),
            self::SECTION_REDEMPTIONS => $this->importRedemptions($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportCoupons(): array
    {
        $rows = [];
        Coupon::query()
            ->orderBy(CouponSchema::ID)
            ->chunkById(200, function ($coupons) use (&$rows): void {
                foreach ($coupons as $coupon) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $coupon->getKey(),
                        self::KEY_PAYLOAD => [
                            // The runtime counter stays behind — imports
                            // replay configuration, not usage state.
                            CouponSchema::CODE => $coupon->{CouponSchema::CODE},
                            CouponSchema::NAME => $coupon->{CouponSchema::NAME},
                            CouponSchema::TYPE => $coupon->{CouponSchema::TYPE}->value,
                            CouponSchema::VALUE => $coupon->{CouponSchema::VALUE},
                            CouponSchema::MAX_DISCOUNT_AMOUNT => $coupon->{CouponSchema::MAX_DISCOUNT_AMOUNT},
                            CouponSchema::MIN_ORDER_AMOUNT => $coupon->{CouponSchema::MIN_ORDER_AMOUNT},
                            CouponSchema::STARTS_AT => $coupon->{CouponSchema::STARTS_AT}?->toIso8601String(),
                            CouponSchema::ENDS_AT => $coupon->{CouponSchema::ENDS_AT}?->toIso8601String(),
                            CouponSchema::USAGE_LIMIT => $coupon->{CouponSchema::USAGE_LIMIT},
                            CouponSchema::PER_USER_LIMIT => $coupon->{CouponSchema::PER_USER_LIMIT},
                            CouponSchema::IS_ACTIVE => (bool) $coupon->{CouponSchema::IS_ACTIVE},
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportPromotions(): array
    {
        $rows = [];
        Promotion::query()
            ->orderBy(PromotionSchema::ID)
            ->chunkById(200, function ($promotions) use (&$rows): void {
                foreach ($promotions as $promotion) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $promotion->getKey(),
                        self::KEY_PAYLOAD => [
                            PromotionSchema::NAME => $promotion->{PromotionSchema::NAME},
                            PromotionSchema::SCOPE => $promotion->{PromotionSchema::SCOPE}->value,
                            PromotionSchema::TYPE => $promotion->{PromotionSchema::TYPE}->value,
                            PromotionSchema::VALUE => $promotion->{PromotionSchema::VALUE},
                            PromotionSchema::STARTS_AT => $promotion->{PromotionSchema::STARTS_AT}?->toIso8601String(),
                            PromotionSchema::ENDS_AT => $promotion->{PromotionSchema::ENDS_AT}?->toIso8601String(),
                            PromotionSchema::PRIORITY => $promotion->{PromotionSchema::PRIORITY},
                            PromotionSchema::IS_ACTIVE => (bool) $promotion->{PromotionSchema::IS_ACTIVE},
                            // Catalog selections as the exporting store's ids.
                            PromotionSchema::PRODUCT_IDS => $promotion->selectedProductIds()->values()->all(),
                            PromotionSchema::CATEGORY_IDS => $promotion->selectedCategoryIds()->values()->all(),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRedemptions(): array
    {
        $rows = [];
        Redemption::query()
            ->with(RedemptionSchema::RES_COUPON)
            ->orderBy(RedemptionSchema::ID)
            ->chunkById(200, function ($redemptions) use (&$rows): void {
                foreach ($redemptions as $redemption) {
                    $rows[] = [
                        self::KEY_SOURCE_ID => (int) $redemption->getKey(),
                        self::KEY_PAYLOAD => [
                            // The code travels; order/user stay source ids.
                            'coupon_code' => $redemption->coupon->{CouponSchema::CODE},
                            RedemptionSchema::ORDER_ID => $redemption->{RedemptionSchema::ORDER_ID},
                            RedemptionSchema::USER_ID => $redemption->{RedemptionSchema::USER_ID},
                            RedemptionSchema::DISCOUNT_AMOUNT => $redemption->{RedemptionSchema::DISCOUNT_AMOUNT},
                            'created_at' => $redemption->{RedemptionSchema::CREATED_AT}?->toIso8601String(),
                        ],
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importCoupons(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_COUPONS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            // Codes are stored uppercase-trimmed, exactly like the request.
            $payload[CouponSchema::CODE] = mb_strtoupper(trim((string) ($payload[CouponSchema::CODE] ?? '')));

            try {
                $existing = Coupon::query()
                    ->where(CouponSchema::CODE, $payload[CouponSchema::CODE])
                    ->first();

                $validator = Validator::make($payload, $this->couponRules($existing?->getKey()));
                if ($validator->fails()) {
                    $result->recordFailed($label.': '.$validator->errors()->first());

                    continue;
                }

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    // Write + remap record share the import unit (C7/F11).
                    $this->remapUnit($options, Module::NAME_LOWER, self::SECTION_COUPONS, (int) $row[self::KEY_SOURCE_ID], function () use ($existing, $payload): int {
                        $this->updateCoupon->handle((string) $existing->getKey(), $payload);

                        return (int) $existing->getKey();
                    });
                    $result->recordUpdated();

                    continue;
                }

                // Write + remap record share the import unit (C7/F11).
                $this->remapUnit($options, Module::NAME_LOWER, self::SECTION_COUPONS, (int) $row[self::KEY_SOURCE_ID], function () use ($payload): int {
                    $record = $this->createCoupon->handle($payload);

                    return (int) $record->getKey();
                });
                $result->recordCreated();
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPromotions(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_PROMOTIONS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                // Catalog refs resolve through the C2 tiers (remap → raw,
                // identified or explicitly allowed) before shape validation.
                $payload = $this->resolveCatalogRefs($options, $payload, $result, $label);
                if ($payload === null) {
                    continue;
                }

                $validator = Validator::make($payload, $this->promotionRules());
                if ($validator->fails()) {
                    $result->recordFailed($label.': '.$validator->errors()->first());

                    continue;
                }

                $existing = Promotion::query()
                    ->where(PromotionSchema::NAME, $payload[PromotionSchema::NAME])
                    ->first();

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    // Write + remap record share the import unit (C7/F11).
                    $this->remapUnit($options, Module::NAME_LOWER, self::SECTION_PROMOTIONS, (int) $row[self::KEY_SOURCE_ID], function () use ($existing, $payload): int {
                        $this->updatePromotion->handle((string) $existing->getKey(), $payload);

                        return (int) $existing->getKey();
                    });
                    $result->recordUpdated();

                    continue;
                }

                // Write + remap record share the import unit (C7/F11).
                $this->remapUnit($options, Module::NAME_LOWER, self::SECTION_PROMOTIONS, (int) $row[self::KEY_SOURCE_ID], function () use ($payload): int {
                    $record = $this->createPromotion->handle($payload);

                    return (int) $record->getKey();
                });
                $result->recordCreated();
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Mirrors CouponRequest (code shape + uniqueness, ignoring the record
     * an UPDATE strategy would overwrite).
     *
     * @return array<string, list<mixed>>
     */
    private function couponRules(int|string|null $ignoreId): array
    {
        return [
            CouponSchema::CODE => [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique(CouponSchema::TABLE, CouponSchema::CODE)->ignore($ignoreId),
            ],
            CouponSchema::NAME => ['nullable', 'string', 'max:255'],
            CouponSchema::TYPE => ['required', Rule::enum(CouponTypeEnum::class)],
            CouponSchema::VALUE => ['required', 'numeric', 'gt:0'],
            CouponSchema::MAX_DISCOUNT_AMOUNT => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            CouponSchema::MIN_ORDER_AMOUNT => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            CouponSchema::STARTS_AT => ['nullable', 'date'],
            CouponSchema::ENDS_AT => ['nullable', 'date'],
            CouponSchema::USAGE_LIMIT => ['nullable', 'integer', 'gt:0', 'max:4294967295'],
            CouponSchema::PER_USER_LIMIT => ['nullable', 'integer', 'gt:0', 'max:4294967295'],
            CouponSchema::IS_ACTIVE => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mirrors PromotionRequest: scope selections are validated through
     * the Catalog gateway, never by touching Catalog's tables.
     *
     * @return array<string, list<mixed>>
     */
    /**
     * The promotion's catalog selections resolve through the C2 tiers
     * (remap → raw, the raw tier gated by the file's identity) BEFORE
     * shape validation, so the admin request's existence checks see local
     * ids. Any unresolvable reference fails the row — never guess.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null null when the row already failed
     */
    private function resolveCatalogRefs(ImportOptions $options, array $payload, ImportResult $result, string $label): ?array
    {
        foreach ([
            PromotionSchema::PRODUCT_IDS => ['entity' => 'products', 'exists' => fn (int $id): bool => $this->catalogGateway->productExists($id)],
            PromotionSchema::CATEGORY_IDS => ['entity' => 'categories', 'exists' => fn (int $id): bool => $this->catalogGateway->categoryExists($id)],
        ] as $field => $spec) {
            $refs = $payload[$field] ?? null;

            if (! is_array($refs)) {
                continue;
            }

            $resolved = [];
            foreach ($refs as $ref) {
                $resolution = $this->catalogResolver->resolve(
                    $options,
                    'catalog',
                    $spec['entity'],
                    (int) $ref,
                    null,
                    $spec['exists'],
                    nullable: false,
                );

                if ($resolution->failed()) {
                    $result->recordFailed($label.': '.$resolution->error);

                    return null;
                }

                if ($resolution->warning !== null) {
                    $result->warn($label.': '.$resolution->warning);
                }

                $resolved[] = $resolution->localId;
            }

            $payload[$field] = $resolved;
        }

        return $payload;
    }

    /**
     * Redemption history (C5 two-pass restore): the coupon resolves by
     * its portable code, the order/user through the ledger written by
     * their own imports. Rows whose order hasn't been imported yet skip
     * with the expected `pending_order_import` warning — pass 1 of a
     * restore is incomplete BY DESIGN, and the re-import (after Order)
     * resolves them via the remap. Redemptions are keyless, so the
     * remap is the idempotency key; `usage_count` is recomputed from the
     * LOCAL dataset after the section (never incremented per-row).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importRedemptions(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_REDEMPTIONS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                // Remap-as-idempotency: re-import never duplicates.
                $remappedId = $this->transferRemaps->resolve($options->sourceInstallation, Module::NAME_LOWER, 'redemptions', (int) $sourceId);
                if ($remappedId !== null && Redemption::query()->whereKey($remappedId)->exists()) {
                    if ($options->dryRun) {
                        $this->recordOutcome($result, $options, true);

                        continue;
                    }

                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                // The coupon by its portable code — the config section of
                // this file (or an earlier import) guarantees it exists.
                $couponId = $this->discountGateway->couponIdByCode(
                    trim((string) ($payload['coupon_code'] ?? '')),
                );
                if ($couponId === null) {
                    $result->recordFailed($label.': unknown_coupon:'.($payload['coupon_code'] ?? '?'));

                    continue;
                }

                $localOrderId = $this->transferRemaps->resolve($options->sourceInstallation, 'order', 'orders', (int) ($payload[RedemptionSchema::ORDER_ID] ?? 0));
                if ($localOrderId === null || ! DB::table('order_orders')->where('id', $localOrderId)->exists()) {
                    // Pass 1 of the documented two-pass restore: the order
                    // section hasn't imported yet — expected, not a failure.
                    $result->recordSkipped();
                    $result->warn($label.': pending_order_import');

                    continue;
                }

                $localUserId = $this->transferRemaps->resolve($options->sourceInstallation, 'user', 'users', (int) ($payload[RedemptionSchema::USER_ID] ?? 0));
                if ($localUserId === null || ! DB::table('auth_users')->where('id', $localUserId)->exists()) {
                    $result->recordSkipped();
                    $result->warn($label.': pending_user_import');

                    continue;
                }

                if ($options->dryRun) {
                    $result->recordCreated();

                    continue;
                }

                $values = [
                    RedemptionSchema::COUPON_ID => $couponId,
                    RedemptionSchema::ORDER_ID => $localOrderId,
                    RedemptionSchema::USER_ID => $localUserId,
                    RedemptionSchema::DISCOUNT_AMOUNT => $payload[RedemptionSchema::DISCOUNT_AMOUNT] ?? '0.00',
                    RedemptionSchema::CREATED_AT => $payload['created_at'] ?? now()->toIso8601String(),
                    RedemptionSchema::UPDATED_AT => $payload['created_at'] ?? now()->toIso8601String(),
                ];

                // Historical write + remap record share the unit (C7/F11);
                // the checkout path (recordRedemption) is NEVER replayed —
                // no capacity is consumed and no gateway logic runs.
                $this->transferUnits->run(function (TransferRunContext $context) use ($values, $sourceId): void {
                    $localId = (int) DB::table(RedemptionSchema::TABLE)->insertGetId($values);
                    $this->transferRemaps->record($context->sourceInstallation, Module::NAME_LOWER, 'redemptions', (int) $sourceId, $localId);
                });

                $result->recordCreated();
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        // The runtime counter is derived state: recompute from the LOCAL
        // dataset (import replays history, not counters — C1 payload rule).
        if (! $options->dryRun) {
            DB::table(CouponSchema::TABLE)->update([
                CouponSchema::USAGE_COUNT => DB::raw('(SELECT COUNT(*) FROM '.RedemptionSchema::TABLE.' WHERE '.RedemptionSchema::COUPON_ID.' = '.CouponSchema::TABLE.'.'.CouponSchema::ID.')'),
            ]);
        }

        return $result;
    }

    private function promotionRules(): array
    {
        return [
            PromotionSchema::NAME => ['required', 'string', 'max:255'],
            PromotionSchema::SCOPE => ['required', Rule::enum(ScopeTypeEnum::class)],
            PromotionSchema::TYPE => ['required', Rule::enum(CouponTypeEnum::class)],
            PromotionSchema::VALUE => ['required', 'numeric', 'gt:0'],
            PromotionSchema::STARTS_AT => ['nullable', 'date'],
            PromotionSchema::ENDS_AT => ['nullable', 'date'],
            PromotionSchema::PRIORITY => ['nullable', 'integer', 'min:0', 'max:65535'],
            PromotionSchema::IS_ACTIVE => ['nullable', 'boolean'],
            PromotionSchema::PRODUCT_IDS => ['nullable', 'array', 'max:1000'],
            PromotionSchema::PRODUCT_IDS.'.*' => [
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->catalogGateway->productExists((int) $value)) {
                        $fail('The product does not exist.');
                    }
                },
            ],
            PromotionSchema::CATEGORY_IDS => ['nullable', 'array', 'max:1000'],
            PromotionSchema::CATEGORY_IDS.'.*' => [
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->catalogGateway->categoryExists((int) $value)) {
                        $fail('The category does not exist.');
                    }
                },
            ],
        ];
    }

    /**
     * Count a row's would-be outcome for a dry run.
     */
    private function recordOutcome(ImportResult $result, ImportOptions $options, bool $exists): void
    {
        if (! $exists) {
            $result->recordCreated();
        } elseif ($options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } else {
            $result->recordUpdated();
        }
    }
}
