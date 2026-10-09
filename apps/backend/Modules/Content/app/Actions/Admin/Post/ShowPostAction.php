<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\Post\PostSchema;

class ShowPostAction extends AbstractPostAction
{
    public function handle(string $id): Model
    {
        return $this->model
            ->with([
                PostSchema::RES_TRANSLATIONS,
                PostSchema::RES_CATEGORIES,
                PostSchema::RES_IMAGES,
                PostSchema::RES_SEO,
            ])
            ->findOrFail($id);
    }
}
