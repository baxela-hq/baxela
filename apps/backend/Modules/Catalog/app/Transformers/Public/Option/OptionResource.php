<?php

namespace Modules\Catalog\Transformers\Public\Option;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema as OTSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class OptionResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the option into a filter-friendly group: the option title
     * plus its values with translated titles.
     */
    public function toArray(Request $request): array
    {
        $translation = $this->resource->translations
            ->firstWhere(OTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
            ?? $this->resource->translations->first();

        return [
            'id' => $this->resource->{OptionSchema::ID},
            'title' => $translation?->{OTSchema::TITLE},
            OptionSchema::RES_VALUES => $this->resource->{OptionSchema::RES_VALUES}
                ->map(function ($value) use ($request): array {
                    $valueTranslation = $value->translations
                        ->firstWhere(OVTSchema::LANGUAGE_ID, $this->resolvePublicLanguageId($request))
                        ?? $value->translations->first();

                    return [
                        'id' => $value->{OptionValueSchema::ID},
                        'title' => $valueTranslation?->{OVTSchema::TITLE},
                    ];
                })
                ->values(),
        ];
    }
}
