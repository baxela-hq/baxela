<?php

namespace Modules\Menu\Transformers\Admin\MenuLink;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;
use Modules\Menu\Models\MenuLinkTranslation;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema as Schema;

/** @mixin MenuLinkTranslation */
class MenuLinkTranslationResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    /**
     * Transform the resource into an array.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            Schema::LANGUAGE_ID => $this->resource->{Schema::LANGUAGE_ID},
            Schema::COL_LANGUAGE => $this->languageCode($this->resource->{Schema::LANGUAGE_ID}),
            Schema::TITLE => $this->resource->{Schema::TITLE},
            Schema::DESCRIPTION => $this->resource->{Schema::DESCRIPTION},
        ];
    }
}
