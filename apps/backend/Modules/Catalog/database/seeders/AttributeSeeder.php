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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        //        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();
        $langs = ['en'];

        foreach ($langs as $lang) {
            $groups = Lang::get(Module::NAME_LOWER.'::seeder.attribute_groups', [], $lang);
            $templates = Lang::get(Module::NAME_LOWER.'::seeder.attribute_templates', [], $lang);

            AttributeValueTranslation::query()->delete();
            AttributeValue::query()->delete();
            AttributeTranslation::query()->delete();
            Attribute::query()->delete();
            AttributeGroupTranslation::query()->delete();
            AttributeGroup::query()->delete();
            AttributeTemplate::query()->delete();

            $this->generate($groups, $templates, $lang);
        }
    }

    /**
     * @param  array{title: string, attributes: array}  $groups
     * @param  array{title: string, description: string, groups: array}  $templates
     */
    private function generate(array $groups, array $templates, string $lang): void
    {
        $languageId = $this->coreGateway->getLanguageIdByCode($lang);

        if (is_null($languageId)) {
            return;
        }

        $groupIds = [];

        foreach ($groups as $i => $group) {
            $attributeGroup = AttributeGroup::query()->create([
                AttributeGroupSchema::POSITION => $i + 1,
            ]);
            $groupIds[$group[AGTSchema::TITLE]] = $attributeGroup->{AttributeGroupSchema::ID};

            AttributeGroupTranslation::query()->create([
                AGTSchema::ATTRIBUTE_GROUP_ID => $attributeGroup->{AttributeGroupSchema::ID},
                AGTSchema::LANGUAGE_ID => $languageId,
                AGTSchema::TITLE => $group[AGTSchema::TITLE],
            ]);

            foreach ($group[AttributeGroupSchema::RES_ATTRIBUTES] as $j => $attribute) {
                $attributeRecord = Attribute::query()->create([
                    AttributeSchema::GROUP_ID => $attributeGroup->{AttributeGroupSchema::ID},
                    AttributeSchema::CODE => $attribute[AttributeSchema::CODE],
                    AttributeSchema::DATA_TYPE => $attribute[AttributeSchema::DATA_TYPE],
                    AttributeSchema::IS_FILTERABLE => $attribute[AttributeSchema::IS_FILTERABLE],
                    AttributeSchema::POSITION => $j + 1,
                ]);

                AttributeTranslation::query()->create([
                    ATSchema::ATTRIBUTE_ID => $attributeRecord->{AttributeSchema::ID},
                    ATSchema::LANGUAGE_ID => $languageId,
                    ATSchema::TITLE => $attribute[ATSchema::TITLE],
                ]);

                foreach ($attribute[AttributeSchema::RES_VALUES] ?? [] as $k => $value) {
                    $attributeValue = AttributeValue::query()->create([
                        AttributeValueSchema::ATTRIBUTE_ID => $attributeRecord->{AttributeSchema::ID},
                        AttributeValueSchema::POSITION => $k + 1,
                    ]);

                    AttributeValueTranslation::query()->create([
                        AVTSchema::ATTRIBUTE_VALUE_ID => $attributeValue->{AttributeValueSchema::ID},
                        AVTSchema::LANGUAGE_ID => $languageId,
                        AVTSchema::TITLE => $value[AVTSchema::TITLE],
                    ]);
                }
            }
        }

        foreach ($templates as $i => $template) {
            $attributeTemplate = AttributeTemplate::query()->create([
                AttributeTemplateSchema::TITLE => $template[AttributeTemplateSchema::TITLE],
                AttributeTemplateSchema::DESCRIPTION => $template[AttributeTemplateSchema::DESCRIPTION],
                AttributeTemplateSchema::IS_ACTIVE => true,
                AttributeTemplateSchema::POSITION => $i + 1,
            ]);

            $attach = [];
            foreach ($template[AttributeTemplateSchema::RES_GROUPS] as $j => $groupTitle) {
                $attach[$groupIds[$groupTitle]] = [AttributeTemplateGroupSchema::POSITION => $j + 1];
            }
            $attributeTemplate->groups()->attach($attach);
        }
    }
}
