<?php

namespace Modules\Catalog\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportSchema;
use Modules\Catalog\Schemas\CatalogImport\CatalogImportStrategyEnum;
use Modules\Catalog\Support\ProductImport\ProductImportFields;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class ImportProductsRequest extends FormRequest
{
    public function __construct(protected CoreGatewayInterface $coreGateway)
    {
        parent::__construct();
    }

    protected function prepareForValidation(): void
    {
        // Fixed payload shape (no `sometimes`): defaults are merged so
        // the action always sees all five keys.
        $this->merge([
            CatalogImportSchema::REQ_ON_DUPLICATE => $this->input(CatalogImportSchema::REQ_ON_DUPLICATE)
                ?? CatalogImportStrategyEnum::UPDATE->value,
            CatalogImportSchema::REQ_DRY_RUN => (bool) $this->input(CatalogImportSchema::REQ_DRY_RUN, false),
            CatalogImportSchema::REQ_CREATE_MISSING_OPTIONS => (bool) $this->input(
                CatalogImportSchema::REQ_CREATE_MISSING_OPTIONS, true),
        ]);
    }

    public function rules(): array
    {
        return [
            CatalogImportSchema::REQ_MEDIA_ID => ['required', 'integer'],
            CatalogImportSchema::REQ_MAPPING => ['required', 'array', 'min:1'],
            CatalogImportSchema::REQ_MAPPING.'.*' => ['nullable', 'string'],
            CatalogImportSchema::REQ_ON_DUPLICATE => ['required', Rule::in([
                CatalogImportStrategyEnum::UPDATE->value,
                CatalogImportStrategyEnum::SKIP->value,
            ])],
            CatalogImportSchema::REQ_DRY_RUN => ['required', 'boolean'],
            CatalogImportSchema::REQ_CREATE_MISSING_OPTIONS => ['required', 'boolean'],
        ];
    }

    /**
     * Mapping values must be real field keys (language-aware), every
     * required field mapped exactly once, nothing mapped twice.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $languageCodes = $this->coreGateway->getActiveLanguages()
                ->pluck(LanguageSchema::CODE)
                ->values()
                ->all();
            $default = $this->coreGateway->getDefaultLanguage()?->code ?? ($languageCodes[0] ?? 'en');

            $allowed = ProductImportFields::all($languageCodes);
            $mapping = (array) $this->input(CatalogImportSchema::REQ_MAPPING, []);

            $mappedFields = [];
            foreach ($mapping as $header => $field) {
                if ($field === null || $field === '') {
                    continue;
                }

                if (! in_array($field, $allowed, true)) {
                    $validator->errors()->add(
                        CatalogImportSchema::REQ_MAPPING.'.'.$header,
                        "The field {$field} does not exist.",
                    );

                    continue;
                }

                $mappedFields[] = $field;
            }

            $duplicates = array_keys(array_filter(array_count_values($mappedFields), fn ($count) => $count > 1));
            foreach ($duplicates as $field) {
                $validator->errors()->add(
                    CatalogImportSchema::REQ_MAPPING,
                    "The field {$field} is mapped more than once.",
                );
            }

            foreach (ProductImportFields::required($default) as $field) {
                if (! in_array($field, $mappedFields, true)) {
                    $validator->errors()->add(
                        CatalogImportSchema::REQ_MAPPING,
                        "The field {$field} must be mapped.",
                    );
                }
            }
        });
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
