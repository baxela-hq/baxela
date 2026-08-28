<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Models\AttributeGroupTranslation;
use Modules\Catalog\Models\AttributeTemplate;
use Modules\Catalog\Models\AttributeTranslation;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Models\AttributeValueTranslation;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema as ATSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema as AGTSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as AVTSchema;
use Modules\Catalog\Schemas\Module;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class AttributeSeeder extends Seeder
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

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $groupsData = [];
        $templatesData = [];
        foreach ($langs as $lang) {
            $groupsData[$lang] = Lang::get(Module::NAME_LOWER.'::seeder.attribute_groups', [], $lang) ?? [];
            $templatesData[$lang] = Lang::get(Module::NAME_LOWER.'::seeder.attribute_templates', [], $lang) ?? [];
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $groups = $groupsData[$masterLang];
        $templates = $templatesData[$masterLang];

        AttributeValueTranslation::query()->delete();
        AttributeValue::query()->delete();
        AttributeTranslation::query()->delete();
        Attribute::query()->delete();
        AttributeGroupTranslation::query()->delete();
        AttributeGroup::query()->delete();
        AttributeTemplate::query()->delete();

        $this->generate($groups, $templates, $groupsData, $langs);
    }

    /**
     * @param  array<string, array{translations: array, attributes: array}>  $groups
     * @param  array<string, array{description: string, groups: array}>  $templates
     * @param  array<string, array<string, array{translations: array, attributes: array}>>  $groupsData
     * @param  array<int, string>  $langs
     */
    private function generate(array $groups, array $templates, array $groupsData, array $langs): void
    {
        $groupIds = [];

        $i = 1;
        foreach ($groups as $groupKey => $group) {
            $attributeGroup = AttributeGroup::query()->create([
                AttributeGroupSchema::POSITION => $i,
            ]);
            $groupId = $attributeGroup->{AttributeGroupSchema::ID};
            $groupIds[$groupKey] = $groupId;

            foreach ($langs as $lang) {
                foreach ($groupsData[$lang][$groupKey]['translations'] ?? [] as $translation) {
                    AttributeGroupTranslation::query()->create([
                        AGTSchema::ATTRIBUTE_GROUP_ID => $groupId,
                        AGTSchema::LANGUAGE_ID => $this->languageId($lang),
                        AGTSchema::TITLE => $translation[AGTSchema::TITLE],
                    ]);
                }
            }

            $j = 1;
            foreach ($group[AttributeGroupSchema::RES_ATTRIBUTES] as $code => $attribute) {
                $attributeRecord = Attribute::query()->create([
                    AttributeSchema::GROUP_ID => $groupId,
                    AttributeSchema::CODE => $code,
                    AttributeSchema::DATA_TYPE => $attribute[AttributeSchema::DATA_TYPE],
                    AttributeSchema::IS_FILTERABLE => $attribute[AttributeSchema::IS_FILTERABLE],
                    AttributeSchema::POSITION => $j,
                ]);
                $attributeId = $attributeRecord->{AttributeSchema::ID};

                foreach ($langs as $lang) {
                    foreach ($groupsData[$lang][$groupKey]['attributes'][$code]['translations'] ?? [] as $translation) {
                        AttributeTranslation::query()->create([
                            ATSchema::ATTRIBUTE_ID => $attributeId,
                            ATSchema::LANGUAGE_ID => $this->languageId($lang),
                            ATSchema::TITLE => $translation[ATSchema::TITLE],
                        ]);
                    }
                }

                $k = 1;
                foreach (array_keys($attribute[AttributeSchema::RES_VALUES] ?? []) as $valueKey) {
                    $attributeValue = AttributeValue::query()->create([
                        AttributeValueSchema::ATTRIBUTE_ID => $attributeId,
                        AttributeValueSchema::POSITION => $k,
                    ]);
                    $attributeValueId = $attributeValue->{AttributeValueSchema::ID};

                    foreach ($langs as $lang) {
                        foreach ($groupsData[$lang][$groupKey]['attributes'][$code]['values'][$valueKey]['translations'] ?? [] as $translation) {
                            AttributeValueTranslation::query()->create([
                                AVTSchema::ATTRIBUTE_VALUE_ID => $attributeValueId,
                                AVTSchema::LANGUAGE_ID => $this->languageId($lang),
                                AVTSchema::TITLE => $translation[AVTSchema::TITLE],
                            ]);
                        }
                    }
                    $k++;
                }
                $j++;
            }
            $i++;
        }

        $n = 1;
        foreach ($templates as $templateKey => $template) {
            $attributeTemplate = AttributeTemplate::query()->create([
                AttributeTemplateSchema::TITLE => $templateKey,
                AttributeTemplateSchema::DESCRIPTION => $template[AttributeTemplateSchema::DESCRIPTION] ?? null,
                AttributeTemplateSchema::IS_ACTIVE => true,
                AttributeTemplateSchema::POSITION => $n,
            ]);

            $attach = [];
            foreach ($template[AttributeTemplateSchema::RES_GROUPS] as $m => $groupTitle) {
                if (isset($groupIds[$groupTitle])) {
                    $attach[$groupIds[$groupTitle]] = [AttributeTemplateGroupSchema::POSITION => $m + 1];
                }
            }
            $attributeTemplate->groups()->attach($attach);
            $n++;
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
