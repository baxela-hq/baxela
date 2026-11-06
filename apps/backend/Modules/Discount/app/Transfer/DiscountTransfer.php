<?php

namespace Modules\Discount\Transfer;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Discount\Actions\Admin\Coupon\CreateCouponAction;
use Modules\Discount\Actions\Admin\Coupon\UpdateCouponAction;
use Modules\Discount\Actions\Admin\Promotion\CreatePromotionAction;
use Modules\Discount\Actions\Admin\Promotion\UpdatePromotionAction;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;
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
    public const string SECTION_COUPONS = 'coupons';

    public const string SECTION_PROMOTIONS = 'promotions';

    public const string SECTION_REDEMPTIONS = 'redemptions';

    public function __construct(
        protected CreateCouponAction $createCoupon,
        protected UpdateCouponAction $updateCoupon,
        protected CreatePromotionAction $createPromotion,
        protected UpdatePromotionAction $updatePromotion,
        protected CatalogGatewayInterface $catalogGateway,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_COUPONS),
            TransferSection::full(self::SECTION_PROMOTIONS),
            TransferSection::exportOnly(self::SECTION_REDEMPTIONS),
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
            // Export-only: the Transfer engine skips this section before
            // it ever reaches here.
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
                            'coupon_code' => $redemption->coupon?->{CouponSchema::CODE},
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

                    $this->updateCoupon->handle((string) $existing->getKey(), $payload);
                    $result->recordUpdated();

                    continue;
                }

                $this->createCoupon->handle($payload);
                $result->recordCreated();
            } catch (Throwable $e) {
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

                    $this->updatePromotion->handle((string) $existing->getKey(), $payload);
                    $result->recordUpdated();

                    continue;
                }

                $this->createPromotion->handle($payload);
                $result->recordCreated();
            } catch (Throwable $e) {
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
