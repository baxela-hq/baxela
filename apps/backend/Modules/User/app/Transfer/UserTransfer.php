<?php

namespace Modules\User\Transfer;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Support\Transfer\CrossModuleResolver;
use Modules\Core\Support\Transfer\RecordsTransferRemaps;
use Modules\Core\Support\Transfer\TransferRunContext;
use Modules\User\Models\Address;
use Modules\User\Models\Profile;
use Modules\User\Models\WishlistItem;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Module;
use Modules\User\Schemas\Profile\ProfileSchema;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;
use Throwable;

/**
 * User module's implementation of the data-transfer standard (C6).
 *
 * The `profiles` section is the user DIRECTORY: one row per auth user,
 * carrying their email (identity lives in Auth, exported through the
 * Access gateway) plus `has_profile` and the profile fields (null when no
 * profile row exists — profile rows are created lazily, so absence is
 * meaningful and must survive the round trip). Addresses and wishlist
 * items reference users by the exporting store's ids; the import resolves
 * people by EMAIL — the one portable identity — and attaches rows to the
 * local accounts, creating inert placeholder accounts (inactive,
 * unverified, no roles) for emails the destination store has never seen.
 *
 * The importer writes models directly: no admin create/update actions
 * exist for profiles/addresses (the self-service actions stamp
 * Auth::id()), and replaying them would attach data to the acting admin.
 */
class UserTransfer implements ModuleTransferInterface
{
    use RecordsTransferRemaps;

    public const string SECTION_PROFILES = 'profiles';

    public const string SECTION_ADDRESSES = 'addresses';

    public const string SECTION_WISHLIST_ITEMS = 'wishlist-items';

    /**
     * Exported source user id → local user id (or dry-run sentinel).
     *
     * @var array<int, int>
     */
    private array $userIdBySource = [];

    /** Dry-run sentinels for users a real run would create. */
    private int $sentinel = -1;

    public function __construct(
        protected AccessGatewayInterface $accessGateway,
        protected CatalogGatewayInterface $catalogGateway,
        protected CrossModuleResolver $productResolver,
    ) {
        $this->initTransferRecording();
    }

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_PROFILES),
            TransferSection::full(self::SECTION_ADDRESSES),
            TransferSection::full(self::SECTION_WISHLIST_ITEMS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            // The directory: every auth user, their email, and their
            // profile when one exists (has_profile disambiguates an empty
            // profile row from no row at all).
            self::SECTION_PROFILES => $this->exportDirectory(),
            self::SECTION_ADDRESSES => $this->exportRowsOf(
                Address::query()->orderBy(AddressSchema::ID),
                fn ($address) => [
                    AddressSchema::USER_ID => (int) $address->{AddressSchema::USER_ID},
                    AddressSchema::TYPE => $address->{AddressSchema::TYPE}->value,
                    AddressSchema::FULL_NAME => $address->{AddressSchema::FULL_NAME},
                    AddressSchema::PHONE => $address->{AddressSchema::PHONE},
                    AddressSchema::ADDRESS_LINE => $address->{AddressSchema::ADDRESS_LINE},
                    AddressSchema::CITY => $address->{AddressSchema::CITY},
                    AddressSchema::POSTAL_CODE => $address->{AddressSchema::POSTAL_CODE},
                    AddressSchema::COUNTRY_CODE => $address->{AddressSchema::COUNTRY_CODE},
                    // Export completeness: these columns existed on the
                    // model but were missing from earlier payloads.
                    AddressSchema::REGION_CODE => $address->{AddressSchema::REGION_CODE},
                    AddressSchema::VAT_ID => $address->{AddressSchema::VAT_ID},
                    AddressSchema::IS_DEFAULT => (bool) $address->{AddressSchema::IS_DEFAULT},
                ],
            ),
            self::SECTION_WISHLIST_ITEMS => $this->exportRowsOf(
                WishlistItem::query()->orderBy(WishlistItemSchema::ID),
                fn ($item) => [
                    WishlistItemSchema::USER_ID => (int) $item->{WishlistItemSchema::USER_ID},
                    WishlistItemSchema::PRODUCT_ID => (int) $item->{WishlistItemSchema::PRODUCT_ID},
                ],
            ),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_PROFILES => $this->importDirectory($rows, $options),
            self::SECTION_ADDRESSES => $this->importAddresses($rows, $options),
            self::SECTION_WISHLIST_ITEMS => $this->importWishlistItems($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportDirectory(): array
    {
        $emailsById = $this->accessGateway->getUserEmailsByIds($this->accessGateway->listUserIds());
        $profileByUserId = Profile::query()
            ->get()
            ->keyBy(ProfileSchema::USER_ID);

        $rows = [];
        foreach ($emailsById as $userId => $email) {
            $profile = $profileByUserId->get($userId);

            $dateOfBirth = $profile?->{ProfileSchema::DATE_OF_BIRTH}?->format('Y-m-d');

            $rows[] = [
                self::KEY_SOURCE_ID => (int) $userId,
                self::KEY_PAYLOAD => [
                    // The portable identity: everything else attaches by it.
                    'email' => $email,
                    'has_profile' => $profile !== null,
                    ProfileSchema::FULL_NAME => $profile?->{ProfileSchema::FULL_NAME},
                    ProfileSchema::DISPLAY_NAME => $profile?->{ProfileSchema::DISPLAY_NAME},
                    ProfileSchema::BIO => $profile?->{ProfileSchema::BIO},
                    ProfileSchema::AVATAR => $profile?->{ProfileSchema::AVATAR},
                    ProfileSchema::GENDER => $profile?->{ProfileSchema::GENDER}?->value,
                    ProfileSchema::DATE_OF_BIRTH => $dateOfBirth,
                ],
            ];
        }

        return $rows;
    }

    /**
     * Dry-run counting for a matched row under the chosen strategy.
     */
    private function recordOutcome(ImportResult $result, ImportOptions $options, bool $exists): void
    {
        if ($exists && $options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } elseif ($exists) {
            $result->recordUpdated();
        } else {
            $result->recordCreated();
        }
    }

    /**
     * The directory import: resolve or create the local account by email,
     * then upsert the profile row when the source had one. Accounts never
     * gain roles or activity here — Auth owns activation (C6).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importDirectory(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_PROFILES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $email = trim((string) ($payload['email'] ?? ''));
            if ($email === '') {
                // Legacy files (pre-email export) cannot establish identity:
                // never create an account from an unreliable key (C6).
                $result->recordFailed($label.': user_unresolvable_no_email');

                continue;
            }

            $validator = Validator::make($payload, [
                'email' => ['required', 'email'],
                'has_profile' => ['required', 'boolean'],
                ProfileSchema::GENDER => ['nullable', 'in:male,female,other'],
                ProfileSchema::DATE_OF_BIRTH => ['nullable', 'date_format:Y-m-d'],
            ]);
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                // The gateway keys results by the STORED casing, which may
                // differ from the canonical lookup — take the first match.
                $emailMatches = $this->accessGateway->getUserIdsByEmails([mb_strtolower($email)]);
                $existingId = $emailMatches === [] ? null : (int) reset($emailMatches);

                if ($options->dryRun) {
                    // Register the would-be id so addresses/wishlist rows
                    // referencing this user stay resolvable for the run.
                    $this->userIdBySource[(int) $sourceId] = $existingId ?? $this->nextSentinel();

                    if ($existingId === null) {
                        $result->recordCreated();
                    } else {
                        $result->recordUpdated();
                    }

                    continue;
                }

                // Account resolution/creation + profile upsert + remap
                // record share the ownership-verified import unit (C7/F11).
                $localUserId = null;
                $this->transferUnits->run(function (TransferRunContext $context) use ($payload, $email, $existingId, $sourceId, &$localUserId): void {
                    $localUserId = $existingId ?? $this->accessGateway->ensureUserByEmail($email);

                    if ($payload['has_profile'] === true) {
                        $profileFields = array_filter([
                            ProfileSchema::FULL_NAME => $payload[ProfileSchema::FULL_NAME] ?? null,
                            ProfileSchema::DISPLAY_NAME => $payload[ProfileSchema::DISPLAY_NAME] ?? null,
                            ProfileSchema::BIO => $payload[ProfileSchema::BIO] ?? null,
                            ProfileSchema::AVATAR => $payload[ProfileSchema::AVATAR] ?? null,
                            ProfileSchema::GENDER => $payload[ProfileSchema::GENDER] ?? null,
                            ProfileSchema::DATE_OF_BIRTH => $payload[ProfileSchema::DATE_OF_BIRTH] ?? null,
                        ], fn ($value): bool => $value !== null);

                        // 1:1 upsert by the unique user_id.
                        Profile::query()->updateOrCreate(
                            [ProfileSchema::USER_ID => $localUserId],
                            $profileFields,
                        );
                    }

                    $this->transferRemaps->record(
                        $context->sourceInstallation,
                        Module::NAME_LOWER,
                        'users',
                        (int) $sourceId,
                        $localUserId,
                    );
                });

                $this->userIdBySource[(int) $sourceId] = $localUserId;

                if ($existingId === null) {
                    $result->recordCreated();
                } else {
                    $result->recordUpdated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Address identity is the persisted source-id remap (C6) — identical
     * text addresses are legitimately distinct, so no value-tuple dedupe:
     * no remap → create; remap → UPDATE rewrites / SKIP skips.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importAddresses(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_ADDRESSES.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $localUserId = $this->localUserId($payload);
            if ($localUserId === null) {
                $result->recordFailed($label.': unresolved_user:'.($payload[AddressSchema::USER_ID] ?? '?'));

                continue;
            }

            $payload[AddressSchema::USER_ID] = $localUserId;

            $validator = Validator::make($payload, [
                AddressSchema::USER_ID => ['required', 'integer'],
                AddressSchema::TYPE => ['required', 'in:billing,shipping'],
                AddressSchema::FULL_NAME => ['required', 'string', 'max:255'],
                AddressSchema::PHONE => ['required', 'string', 'max:32'],
                AddressSchema::ADDRESS_LINE => ['required', 'string', 'max:255'],
                AddressSchema::CITY => ['required', 'string', 'max:255'],
                AddressSchema::POSTAL_CODE => ['nullable', 'string', 'max:32'],
                AddressSchema::COUNTRY_CODE => ['required', 'string', 'size:2'],
                AddressSchema::REGION_CODE => ['nullable', 'string', 'max:32'],
                AddressSchema::VAT_ID => ['nullable', 'string', 'max:32'],
                AddressSchema::IS_DEFAULT => ['required', 'boolean'],
            ]);
            if ($validator->fails()) {
                $result->recordFailed($label.': '.$validator->errors()->first());

                continue;
            }

            try {
                $existingId = $this->transferRemaps->resolve(
                    $options->sourceInstallation,
                    Module::NAME_LOWER,
                    'addresses',
                    (int) $sourceId,
                );

                if ($existingId !== null && ! Address::query()->whereKey($existingId)->exists()) {
                    $existingId = null; // dangling: recreate (warning-worthy, healed)
                }

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existingId !== null);

                    continue;
                }

                if ($existingId !== null && $options->strategy === TransferStrategyEnum::SKIP) {
                    $result->recordSkipped();

                    continue;
                }

                // Mirror the self-service action's single-default invariant.
                $this->transferUnits->run(function (TransferRunContext $context) use ($payload, $existingId, $sourceId): void {
                    $address = $existingId !== null
                        ? tap(Address::query()->findOrFail($existingId))->update($payload)
                        : Address::query()->create($payload);

                    if ($payload[AddressSchema::IS_DEFAULT] === true) {
                        Address::query()
                            ->where(AddressSchema::USER_ID, $payload[AddressSchema::USER_ID])
                            ->whereKeyNot($address->getKey())
                            ->update([AddressSchema::IS_DEFAULT => false]);
                    }

                    $addressId = (int) $address->getKey();

                    $this->transferRemaps->record(
                        $context->sourceInstallation,
                        Module::NAME_LOWER,
                        'addresses',
                        (int) $sourceId,
                        $addressId,
                    );
                });

                if ($existingId !== null) {
                    $result->recordUpdated();
                } else {
                    $result->recordCreated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Wishlist identity is the unique (user, product) pair — inherently
     * idempotent; product refs resolve through the C2 tiers.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importWishlistItems(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_WISHLIST_ITEMS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $localUserId = $this->localUserId($payload);
            if ($localUserId === null) {
                $result->recordFailed($label.': unresolved_user:'.($payload[WishlistItemSchema::USER_ID] ?? '?'));

                continue;
            }

            $productResolution = $this->productResolver->resolve(
                $options,
                'catalog',
                'products',
                (int) ($payload[WishlistItemSchema::PRODUCT_ID] ?? 0),
                null,
                fn (int $id): bool => $this->catalogGateway->productExists($id),
                nullable: false,
            );

            if ($productResolution->failed()) {
                $result->recordFailed($label.': '.$productResolution->error);

                continue;
            }

            if ($productResolution->warning !== null) {
                $result->warn($label.': '.$productResolution->warning);
            }

            $payload[WishlistItemSchema::USER_ID] = $localUserId;
            $payload[WishlistItemSchema::PRODUCT_ID] = (int) $productResolution->localId;

            try {
                $existing = WishlistItem::query()
                    ->where(WishlistItemSchema::USER_ID, $localUserId)
                    ->where(WishlistItemSchema::PRODUCT_ID, $payload[WishlistItemSchema::PRODUCT_ID])
                    ->first();

                if ($options->dryRun) {
                    $this->recordOutcome($result, $options, $existing !== null);

                    continue;
                }

                if ($existing !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();
                    } else {
                        // The pair IS the data; a matched item is satisfied.
                        $result->recordUpdated();
                    }

                    continue;
                }

                $this->transferUnits->run(function () use ($payload, $localUserId): void {
                    WishlistItem::query()->create([
                        WishlistItemSchema::USER_ID => $localUserId,
                        WishlistItemSchema::PRODUCT_ID => $payload[WishlistItemSchema::PRODUCT_ID],
                    ]);
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
     * The payload's user reference, resolved in-run: profiles ran first
     * (dependency order), so the directory map covers same-file refs;
     * remap covers refs from earlier imports.
     */
    /**
     * @param  array<string, mixed>  $payload
     */
    private function localUserId(array $payload): ?int
    {
        $sourceUserId = $payload[WishlistItemSchema::USER_ID]
            ?? $payload[AddressSchema::USER_ID]
            ?? null;

        if (! is_numeric($sourceUserId)) {
            return null;
        }

        return $this->userIdBySource[(int) $sourceUserId] ?? null;
    }

    private function nextSentinel(): int
    {
        return $this->sentinel--;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRowsOf($query, \Closure $payload): array
    {
        $rows = [];
        $query->chunkById(200, function ($records) use (&$rows, $payload): void {
            foreach ($records as $record) {
                $rows[] = [
                    self::KEY_SOURCE_ID => (int) $record->getKey(),
                    self::KEY_PAYLOAD => $payload($record),
                ];
            }
        });

        return $rows;
    }
}
