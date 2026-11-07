<?php

namespace Modules\User\Transfer;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\User\Models\Address;
use Modules\User\Models\Profile;
use Modules\User\Models\WishlistItem;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Profile\ProfileSchema;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

/**
 * User module's implementation of the data-transfer standard:
 * export-only. Every row belongs to a user (profiles, addresses,
 * wishlist items), and user ids are store-specific — replaying them into
 * another store would attach data to the wrong people, so the module
 * publishes a faithful export and no import surface.
 */
class UserTransfer implements ModuleTransferInterface
{
    public const string SECTION_PROFILES = 'profiles';

    public const string SECTION_ADDRESSES = 'addresses';

    public const string SECTION_WISHLIST_ITEMS = 'wishlist-items';

    public function sections(): array
    {
        return [
            TransferSection::exportOnly(self::SECTION_PROFILES),
            TransferSection::exportOnly(self::SECTION_ADDRESSES),
            TransferSection::exportOnly(self::SECTION_WISHLIST_ITEMS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_PROFILES => $this->exportRowsOf(
                Profile::query()->orderBy(ProfileSchema::ID),
                fn ($profile) => [
                    ProfileSchema::USER_ID => (int) $profile->{ProfileSchema::USER_ID},
                    ProfileSchema::FULL_NAME => $profile->{ProfileSchema::FULL_NAME},
                    ProfileSchema::DISPLAY_NAME => $profile->{ProfileSchema::DISPLAY_NAME},
                    ProfileSchema::BIO => $profile->{ProfileSchema::BIO},
                    ProfileSchema::AVATAR => $profile->{ProfileSchema::AVATAR},
                    ProfileSchema::GENDER => $profile->{ProfileSchema::GENDER}?->value,
                    ProfileSchema::DATE_OF_BIRTH => $profile->{ProfileSchema::DATE_OF_BIRTH}?->format('Y-m-d'),
                ],
            ),
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

    /**
     * Export-only: the Transfer engine skips these sections before it
     * ever reaches here.
     */
    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        throw new InvalidArgumentException("Section [{$section}] is export-only.");
    }

    /**
     * @param  Builder  $query
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
