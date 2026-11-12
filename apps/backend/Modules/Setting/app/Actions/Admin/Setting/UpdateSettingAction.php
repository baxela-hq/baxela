<?php

namespace Modules\Setting\Actions\Admin\Setting;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Setting\SettingUpdatedEvent;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Support\AppliesStoreIdentity;
use Modules\Setting\Support\AppliesStoreTimezone;
use Modules\Setting\Support\ResolvesFxSettings;
use Modules\Setting\Support\ResolvesPricingMode;

class UpdateSettingAction
{
    public function __construct(
        protected Setting $model,
        protected CoreGatewayInterface $coreGateway,
    ) {}

    public function handle(array $data): Collection
    {
        // the boot-time timezone override caches its lookup
        Cache::forget(AppliesStoreTimezone::CACHE_KEY);
        Cache::forget(ResolvesPricingMode::CACHE_KEY);
        Cache::forget(AppliesStoreIdentity::CACHE_KEY);
        Cache::forget(ResolvesFxSettings::CACHE_KEY);

        DB::beginTransaction();

        try {
            foreach ($data as $item) {
                $record = $this->model->where(SettingSchema::NAME, $item[SettingSchema::NAME])->firstOrFail();

                // The FX credential (review F12/F17): an explicit null
                // CLEARS the key, any submitted value REPLACES it (encrypted
                // at rest so database dumps don't carry the plaintext).
                // "Keep" is expressed by OMITTING the item — Laravel's
                // ConvertEmptyStringsToNull turns '' into null on the wire,
                // so an empty submission cannot be distinguished from a
                // deliberate clear. The admin UI omits the field when
                // left blank. The masked placeholder never reaches here
                // (validation rejects it).
                if ($item[SettingSchema::NAME] === SettingNameEnum::FX_API_KEY->value) {
                    $submitted = $item[SettingSchema::VALUE] ?? null;

                    if ($submitted === null) {
                        $record->update([SettingSchema::VALUE => '']);
                    } else {
                        $record->update([
                            SettingSchema::VALUE => Crypt::encryptString((string) $submitted),
                        ]);
                    }
                } else {
                    $record->update([SettingSchema::VALUE => $item[SettingSchema::VALUE]]);
                }

                // The default language/currency settings are the source of
                // truth; mirror them onto the core flags so flag-based
                // consumers (sign-in snapshot, gateways) stay in sync.
                if ($item[SettingSchema::NAME] === SettingNameEnum::LANGUAGE_ID->value) {
                    $this->coreGateway->markLanguageDefault((int) $item[SettingSchema::VALUE]);
                }

                if ($item[SettingSchema::NAME] === SettingNameEnum::CURRENCY_ID->value) {
                    $this->coreGateway->markCurrencyDefault((int) $item[SettingSchema::VALUE]);
                }

                if (isset($item[SettingSchema::RES_TRANSLATIONS])) {
                    $record->translations()->delete();
                    foreach ($item[SettingSchema::RES_TRANSLATIONS] as $translation) {
                        $record->translations()->create($translation);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            throw $e;
        }

        event(new SettingUpdatedEvent);

        return $this->model->with(SettingSchema::RES_TRANSLATIONS)->get();
    }
}
