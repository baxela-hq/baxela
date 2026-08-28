<?php

namespace Modules\Content\Actions\Admin\Page;

use Modules\Content\Models\Page;
use Modules\Content\Schemas\Page\PageSchema;

class CreatePageAction
{
    public function handle(array $data): Page
    {
        $body = [
            PageSchema::STATUS => $data[PageSchema::STATUS],
        ];
        $record = Page::query()->create($body);

        $translations = $data[PageSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        $record = $record->refresh();

        return $record->load(PageSchema::RES_TRANSLATIONS);
    }
}
