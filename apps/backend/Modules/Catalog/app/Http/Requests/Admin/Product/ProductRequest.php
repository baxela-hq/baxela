<?php

namespace Modules\Catalog\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTypeEnum;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Image\ImageCollectionEnum;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Product\DimensionUnitEnum;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema as PAVSchema;
use Modules\Catalog\Schemas\Product\ProductSchema as Schema;
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema as PSTSchema;
use Modules\Catalog\Schemas\Product\ProductShippingSchema as PSSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema as PTSchema;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Catalog\Schemas\Product\WeightUnitEnum;
use Modules\Catalog\Schemas\Variant\VariantSchema as VSchema;
use Modules\Core\Http\Requests\ResolvesLanguagesTrait;
use Modules\Core\Rules\LanguageUniquePair;

class ProductRequest extends FormRequest
{
    use ResolvesLanguagesTrait;

    protected function prepareForValidation(): void
    {
        $this->resolveLanguages();
        $this->resolveLanguagesFor(Schema::RES_SEO);
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            // base
            Schema::TYPE => ['required', new Enum(ProductTypeEnum::class)],
            Schema::STATUS => ['required', new Enum(ProductStatusEnum::class)],
            Schema::IS_PUBLISHED => ['required', 'boolean'],

            // shipping (value and unit must be filled as a pair, or both left null)
            Schema::RES_SHIPPING => ['nullable', 'array'],
            Schema::RES_SHIPPING.'.'.PSSchema::REQUIRES_SHIPPING => ['required_with:'.Schema::RES_SHIPPING, 'boolean'],
            Schema::RES_SHIPPING.'.'.PSSchema::WEIGHT => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            Schema::RES_SHIPPING.'.'.PSSchema::WEIGHT_UNIT => ['nullable',
                'required_with:'.Schema::RES_SHIPPING.'.'.PSSchema::WEIGHT,
                new Enum(WeightUnitEnum::class)],
            Schema::RES_SHIPPING.'.'.PSSchema::PACKAGE_LENGTH => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            Schema::RES_SHIPPING.'.'.PSSchema::PACKAGE_WIDTH => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            Schema::RES_SHIPPING.'.'.PSSchema::PACKAGE_HEIGHT => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            Schema::RES_SHIPPING.'.'.PSSchema::DIMENSION_UNIT => ['nullable',
                'required_with:'.Schema::RES_SHIPPING.'.'.PSSchema::PACKAGE_LENGTH.','
                .Schema::RES_SHIPPING.'.'.PSSchema::PACKAGE_WIDTH.','
                .Schema::RES_SHIPPING.'.'.PSSchema::PACKAGE_HEIGHT,
                new Enum(DimensionUnitEnum::class)],

            // categories
            Schema::RES_CATEGORIES => ['required', 'array', 'max:5'],
            Schema::RES_CATEGORIES.'.*' => ['required', 'integer',
                Rule::exists(CategorySchema::TABLE, CategorySchema::ID)],

            // images
            Schema::RES_IMAGES.'.*' => ['nullable', 'array', 'min:1'],
            Schema::RES_IMAGES.'.*'.ImageSchema::POSITION => ['nullable', 'numeric', 'max:255'],
            Schema::RES_IMAGES.'.*'.ImageSchema::COLLECTION => ['nullable', new Enum(ImageCollectionEnum::class)],
            Schema::RES_IMAGES.'.*'.ImageSchema::MEDIA_ID => ['required', 'integer'],
            Schema::RES_IMAGES.'.*'.ImageSchema::URL => ['required', 'string'],

            // variants
            Schema::RES_VARIANTS.'.*' => ['required', 'array', 'min:1'],
            Schema::RES_VARIANTS.'.*.'.VSchema::SKU => [
                'required',
                'string',
                Rule::unique(VSchema::TABLE, VSchema::SKU)->ignore($id, VSchema::PRODUCT_ID),
                'max:255',
            ],
            Schema::RES_VARIANTS.'.*.'.VSchema::BARCODE => ['sometimes', 'string'],
            Schema::RES_VARIANTS.'.*.'.VSchema::PRICE => ['required', 'string', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            Schema::RES_VARIANTS.'.*.'.VSchema::QUANTITY => ['required', 'integer'],
            Schema::RES_VARIANTS.'.*.'.VSchema::IS_DEFAULT => ['required', 'boolean'],
            Schema::RES_VARIANTS.'.*.'.VSchema::REQ_OPTION_VALUE_IDS => [Rule::requiredIf($this->isTypeVariable()), 'array', Rule::when($this->isTypeVariable(), ['min:1'])],
            Schema::RES_VARIANTS.'.*.'.VSchema::REQ_OPTION_VALUE_IDS.'*' => [Rule::requiredIf($this->isTypeVariable()), Rule::when($this->isTypeVariable(), ['integer'])],

            // attribute values
            PAVSchema::REQ_ATTRIBUTE_VALUES => ['nullable', 'array'],
            PAVSchema::REQ_ATTRIBUTE_VALUES.'.*' => ['required', 'array'],
            PAVSchema::REQ_ATTRIBUTE_VALUES.'.*.'.PAVSchema::ATTRIBUTE_ID => ['required', 'integer',
                Rule::exists(AttributeSchema::TABLE, AttributeSchema::ID)],
            PAVSchema::REQ_ATTRIBUTE_VALUES.'.*.'.PAVSchema::ATTRIBUTE_VALUE_ID => ['nullable', 'integer',
                Rule::exists(AttributeValueSchema::TABLE, AttributeValueSchema::ID)],
            PAVSchema::REQ_ATTRIBUTE_VALUES.'.*.'.PAVSchema::TEXT_VALUE => ['nullable', 'string', 'max:255'],
            PAVSchema::REQ_ATTRIBUTE_VALUES.'.*.'.PAVSchema::NUMBER_VALUE => ['nullable',
                'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            PAVSchema::REQ_ATTRIBUTE_VALUES.'.*.'.PAVSchema::BOOLEAN_VALUE => ['nullable', 'boolean'],

            // translations
            Schema::RES_TRANSLATIONS => ['required', 'array', 'min:1'],
            Schema::RES_TRANSLATIONS.'.*.'.PTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            Schema::RES_TRANSLATIONS.'.*.'.PTSchema::LANGUAGE_ID => ['required', 'integer'],
            Schema::RES_TRANSLATIONS.'.*.'.PTSchema::TITLE => ['required', 'string', 'max:255'],
            Schema::RES_TRANSLATIONS.'.*.'.PTSchema::SLUG => ['required', 'string', 'max:255',
                new LanguageUniquePair(PTSchema::TABLE, PTSchema::SLUG, $this->languageMap, $id)],
            Schema::RES_TRANSLATIONS.'.*.'.PTSchema::CONTENT => ['required', 'string'],
            Schema::RES_TRANSLATIONS.'.*.'.PTSchema::DESCRIPTION => ['nullable', 'string', 'max:255'],

            // seo (language_id is resolved from the language code in prepareForValidation)
            Schema::RES_SEO => ['nullable', 'array'],
            Schema::RES_SEO.'.*.'.PSTSchema::REQ_LANGUAGE => ['required', 'string', 'distinct', 'size:2',
                Rule::in(array_keys($this->languageMap))],
            Schema::RES_SEO.'.*.'.PSTSchema::META_TITLE => ['nullable', 'string', 'max:255'],
            Schema::RES_SEO.'.*.'.PSTSchema::META_DESCRIPTION => ['nullable', 'string', 'max:255'],
            Schema::RES_SEO.'.*.'.PSTSchema::OPEN_GRAPH_TITLE => ['nullable', 'string', 'max:255'],
            Schema::RES_SEO.'.*.'.PSTSchema::OPEN_GRAPH_DESCRIPTION => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Each submitted value must fill the typed column matching the
     * attribute's data_type, and select values must belong to the attribute.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $attributeValues = $this->input(PAVSchema::REQ_ATTRIBUTE_VALUES);

            if (empty($attributeValues)) {
                return;
            }

            $attributes = Attribute::query()
                ->whereIn(AttributeSchema::ID, array_column($attributeValues, PAVSchema::ATTRIBUTE_ID))
                ->get()
                ->keyBy(AttributeSchema::ID);

            foreach ($attributeValues as $index => $attributeValue) {
                $attribute = $attributes->get($attributeValue[PAVSchema::ATTRIBUTE_ID] ?? null);

                if (! $attribute) {
                    continue; // rejected by the exists rule
                }

                $this->validateAttributeValueType($validator, $index, $attribute, $attributeValue);
            }
        });
    }

    private function validateAttributeValueType(Validator $validator, int $index, Attribute $attribute, array $attributeValue): void
    {
        $dataType = $attribute->{AttributeSchema::DATA_TYPE};
        $valueKey = match ($dataType) {
            AttributeTypeEnum::SELECT, AttributeTypeEnum::MULTISELECT => PAVSchema::ATTRIBUTE_VALUE_ID,
            AttributeTypeEnum::NUMBER => PAVSchema::NUMBER_VALUE,
            AttributeTypeEnum::BOOLEAN => PAVSchema::BOOLEAN_VALUE,
            AttributeTypeEnum::TEXT => PAVSchema::TEXT_VALUE,
        };

        $key = PAVSchema::REQ_ATTRIBUTE_VALUES.".$index";

        if (($attributeValue[$valueKey] ?? null) === null) {
            $validator->errors()->add($key,
                "The value for attribute {$attribute->{AttributeSchema::CODE}} must fill {$valueKey} ({$dataType->value} data type).");

            return;
        }

        if (in_array($dataType, [AttributeTypeEnum::SELECT, AttributeTypeEnum::MULTISELECT], true)) {
            $belongsToAttribute = AttributeValue::query()
                ->where(AttributeValueSchema::ID, $attributeValue[PAVSchema::ATTRIBUTE_VALUE_ID])
                ->where(AttributeValueSchema::ATTRIBUTE_ID, $attribute->{AttributeSchema::ID})
                ->exists();

            if (! $belongsToAttribute) {
                $validator->errors()->add($key,
                    "The selected value does not belong to attribute {$attribute->{AttributeSchema::CODE}}.");
            }
        }
    }

    private function isTypeVariable(): bool
    {
        return $this->input(Schema::TYPE) === ProductTypeEnum::VARIABLE->value;
    }

    private function isTypeSimple(): bool
    {
        return $this->input(Schema::TYPE) === ProductTypeEnum::SIMPLE->value;
    }
}
