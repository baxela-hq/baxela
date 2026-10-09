<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\Post\PostSchema;

class ShowPostAction extends AbstractPostAction
{
    use EnrichesPostProductsTrait;

    public function handle(string $id): Model
    {
        $record = $this->model
            ->with([
                PostSchema::RES_TRANSLATIONS,
                PostSchema::RES_CATEGORIES,
                PostSchema::RES_SEO,
                PostSchema::RES_PRODUCTS,
            ])
            ->findOrFail($id);

        $this->enrichWithProductSummaries($record);

        return $record;
    }
}
