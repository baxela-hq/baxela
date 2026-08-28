<?php

namespace Modules\Content\Actions\Public\Page;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;

class ShowPageAction extends AbstractPageAction
{
    public function handle(string $slug): Model
    {
        return $this->model->where([
            PageSchema::SLUG => $slug,
            PageSchema::STATUS => PageStatusEnum::PUBLISHED,
        ])->firstOrFail();
    }
}
