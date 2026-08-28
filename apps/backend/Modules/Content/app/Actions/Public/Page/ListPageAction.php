<?php

namespace Modules\Content\Actions\Public\Page;

use Illuminate\Http\Request;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;

class ListPageAction extends AbstractPageAction
{
    public function handle(Request $request)
    {
        return $this->model->where(PageSchema::STATUS, PageStatusEnum::PUBLISHED)->get();
    }
}
