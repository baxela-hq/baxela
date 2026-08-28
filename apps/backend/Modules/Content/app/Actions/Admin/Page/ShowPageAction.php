<?php

namespace Modules\Content\Actions\Admin\Page;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\Page\PageSchema;

class ShowPageAction extends AbstractPageAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->with(PageSchema::RES_TRANSLATIONS)->findOrFail($id);
    }
}
