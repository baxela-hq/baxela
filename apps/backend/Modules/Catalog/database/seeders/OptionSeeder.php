<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionTranslation;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\OptionValueTranslation;
use Modules\Catalog\Schemas\Category\CategorySchema;
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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        //        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();
        $langs = ['en'];
        $moduleKey = Module::NAME_LOWER.'::seeder.options';

        foreach ($langs as $lang) {

            $options = Lang::get($moduleKey, [], $lang);

            Option::query()->delete();
            OptionTranslation::query()->delete();
            OptionValue::query()->delete();
            OptionValueTranslation::query()->delete();

            $this->generate($options);
        }

    }

    /**
     * @param  array{title: string, slug: string, children: array}  $options
     */
    private function generate(array $options, ?int $parentId = null): void
    {
        $languageId = $this->coreGateway->getLanguageIdByCode(App::currentLocale());

        if (is_null($languageId)) {
            return;
        }

        /* @var array{title: string, slug: string, children: array} $option */
        $i = 1;
        foreach ($options as $option) {
            $options = Option::query()->create([
                OptionSchema::POSITION => $i,
            ]);
            $id = $options->{CategorySchema::ID};
            OptionTranslation::query()->create([
                OTSchema::OPTION_ID => $id,
                OTSchema::LANGUAGE_ID => $languageId,
                OTSchema::TITLE => $option[OTSchema::TITLE],
                OTSchema::SLUG => $option[OTSchema::SLUG],
            ]);
            if (count($option['values'])) {
                $j = 1;
                foreach ($option['values'] as $value) {
                    $optionValue = OptionValue::query()->create([
                        OVSchema::OPTION_ID => $id,
                        OVSchema::POSITION => $j,
                    ]);
                    OptionValueTranslation::query()->create([
                        OVTSchema::LANGUAGE_ID => $languageId,
                        OVTSchema::OPTION_VALUE_ID => $optionValue->{OVSchema::ID},
                        OVTSchema::TITLE => $value[OVTSchema::TITLE],
                        OVTSchema::SLUG => $value[OVTSchema::SLUG],
                    ]);
                    $j++;
                }
            }
            $i++;
        }
    }
}
