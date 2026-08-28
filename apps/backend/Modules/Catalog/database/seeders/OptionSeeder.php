<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionTranslation;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\OptionValueTranslation;
use Modules\Catalog\Schemas\Module;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema as OTSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema as OVSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class OptionSeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    /** @var array<string, int|null> */
    private array $languageIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        $moduleKey = Module::NAME_LOWER.'::seeder.options';

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $data = [];
        foreach ($langs as $lang) {
            $data[$lang] = Lang::get($moduleKey, [], $lang) ?? [];
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $options = $data[$masterLang];

        OptionTranslation::query()->delete();
        OptionValueTranslation::query()->delete();
        OptionValue::query()->delete();
        Option::query()->delete();

        $this->generate($options, $data, $langs);
    }

    /**
     * @param  array<string, array{translations: array, values: array}>  $options
     * @param  array<string, array<string, array{translations: array, values: array}>>  $data
     * @param  array<int, string>  $langs
     */
    private function generate(array $options, array $data, array $langs): void
    {
        $i = 1;

        foreach ($options as $slug => $option) {
            $optionRecord = Option::query()->create([
                OptionSchema::POSITION => $i,
            ]);
            $optionId = $optionRecord->{OptionSchema::ID};

            foreach ($langs as $lang) {
                foreach ($data[$lang][$slug]['translations'] ?? [] as $translation) {
                    OptionTranslation::query()->create([
                        OTSchema::OPTION_ID => $optionId,
                        OTSchema::LANGUAGE_ID => $this->languageId($lang),
                        OTSchema::TITLE => $translation[OTSchema::TITLE],
                        OTSchema::SLUG => $translation[OTSchema::SLUG] ?? $slug,
                    ]);
                }
            }

            $j = 1;
            foreach (array_keys($option[OptionSchema::RES_VALUES] ?? []) as $valueSlug) {
                $optionValue = OptionValue::query()->create([
                    OVSchema::OPTION_ID => $optionId,
                    OVSchema::POSITION => $j,
                ]);
                $optionValueId = $optionValue->{OVSchema::ID};

                foreach ($langs as $lang) {
                    foreach ($data[$lang][$slug]['values'][$valueSlug]['translations'] ?? [] as $translation) {
                        OptionValueTranslation::query()->create([
                            OVTSchema::OPTION_VALUE_ID => $optionValueId,
                            OVTSchema::LANGUAGE_ID => $this->languageId($lang),
                            OVTSchema::TITLE => $translation[OVTSchema::TITLE],
                            OVTSchema::SLUG => $translation[OVTSchema::SLUG] ?? $valueSlug,
                        ]);
                    }
                }
                $j++;
            }
            $i++;
        }
    }

    private function languageId(string $code): ?int
    {
        if (! array_key_exists($code, $this->languageIds)) {
            $this->languageIds[$code] = $this->coreGateway->getLanguageIdByCode($code);
        }

        return $this->languageIds[$code];
    }
}
