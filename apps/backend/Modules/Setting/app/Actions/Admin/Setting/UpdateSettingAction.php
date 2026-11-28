<?php

namespace Modules\Setting\Actions\Admin\Setting;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\Events\Setting\SettingUpdatedEvent;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Support\AppliesStoreIdentity;
use Modules\Setting\Support\AppliesStoreTimezone;
use Modules\Setting\Support\PlatformSettings;
use Modules\Setting\Support\ResolvesFxSettings;
use Modules\Setting\Support\ResolvesPricingMode;

class UpdateSettingAction
{
    public function __construct(
        protected Setting $model,
        protected CoreGatewayInterface $coreGateway,
        protected PlatformSettingsInterface $platformSettings,
        protected AccessGatewayInterface $accessGateway,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $data
     * @return Collection<int, Setting>
     */
    public function handle(array $data): Collection
    {
        // Installation-level names are not store settings: only the
        // platform super-admin may write their NULL-tenant rows (§6.7.1).
        // The admin permission for this shared route is assignable to
        // store roles, so the action enforces the stricter rule itself,
        // before opening a transaction or reporting an expected 403.
        foreach ($data as $item) {
            $name = (string) $item[SettingSchema::NAME];

            if ($name === SettingNameEnum::FX_SYNC_STATUS->value) {
                throw new InvalidArgumentException('The FX sync status is managed by the system and cannot be edited.');
            }

            if (! PlatformSettings::isPlatformName($name)) {
                continue;
            }

            $user = auth()->user();

            if ($user === null || ! $this->accessGateway->isPlatformSuperAdmin($user)) {
                throw new AuthorizationException;
            }

            break;
        }

        // Every store-dependent cache family uses the same tenant prefix
        // as its read path (§6.7 item 6); platform values have their own
        // unprefixed cache and are forgotten by their write method. Only a
        // request that passed the guards above reaches this invalidation.
        Cache::forget(AppliesStoreTimezone::cacheKey());
        Cache::forget(ResolvesPricingMode::cacheKey());
        Cache::forget(AppliesStoreIdentity::cacheKey());
        Cache::forget(ResolvesFxSettings::cacheKey());

        DB::beginTransaction();

        try {
            foreach ($data as $item) {
                $name = (string) $item[SettingSchema::NAME];
                $value = $this->valueFor($item);

                if (PlatformSettings::isPlatformName($name)) {
                    // Platform rows live at tenant_id NULL and are written
                    // through the allowlisted resolver: the tenant scope must
                    // never see them, and its cache is invalidated by write().
                    $this->platformSettings->write($name, $value);

                    continue;
                }

                $record = $this->model->where(SettingSchema::NAME, $name)->firstOrFail();
                $record->update([SettingSchema::VALUE => $value]);

                // The default language/currency settings are the source of
                // truth; mirror them onto the core flags so flag-based
                // consumers (sign-in snapshot, gateways) stay in sync.
                if ($name === SettingNameEnum::LANGUAGE_ID->value) {
                    $this->coreGateway->markLanguageDefault((int) $value);
                }

                if ($name === SettingNameEnum::CURRENCY_ID->value) {
                    $this->coreGateway->markCurrencyDefault((int) $value);
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

    /**
     * The FX credential is encrypted at rest (review F12/F17); an explicit
     * null clears it, and omitting the item keeps the existing row. The
     * masked placeholder is rejected before this point.
     *
     * @param  array<string, mixed>  $item
     */
    protected function valueFor(array $item): string
    {
        $submitted = $item[SettingSchema::VALUE] ?? null;

        if ($item[SettingSchema::NAME] === SettingNameEnum::FX_API_KEY->value) {
            return $submitted === null ? '' : Crypt::encryptString((string) $submitted);
        }

        return (string) $submitted;
    }
}
