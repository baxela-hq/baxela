<?php

namespace Modules\Content\Transformers\Admin\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema as Schema;
use Modules\Core\Transformers\ResolvesLanguageCodesTrait;

class PostSeoTranslationResource extends JsonResource
{
    use ResolvesLanguageCodesTrait;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::LANGUAGE_ID => $this->resource->{Schema::LANGUAGE_ID},
            Schema::COL_LANGUAGE => $this->languageCode($this->resource->{Schema::LANGUAGE_ID}),
            Schema::META_TITLE => $this->resource->{Schema::META_TITLE},
            Schema::META_DESCRIPTION => $this->resource->{Schema::META_DESCRIPTION},
            Schema::OPEN_GRAPH_TITLE => $this->resource->{Schema::OPEN_GRAPH_TITLE},
            Schema::OPEN_GRAPH_DESCRIPTION => $this->resource->{Schema::OPEN_GRAPH_DESCRIPTION},
        ];
    }
}
