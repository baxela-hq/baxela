<?php

namespace Modules\Core\Transformers\Public\Language;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema as Schema;

/**
 * The storefront language switcher's source of truth: languages the store
 * actually offers, with their native names and direction.
 */
class ActiveLanguageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Language $language */
        $language = $this->resource;

        return [
            Schema::CODE => $language->{Schema::CODE},
            Schema::NAME => $language->{Schema::NAME},
            Schema::NATIVE_NAME => $language->{Schema::NATIVE_NAME},
            Schema::IS_RTL => $language->{Schema::IS_RTL},
        ];
    }
}
