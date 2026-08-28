<?php

namespace Modules\Content\Actions\Admin\Page;

use Modules\Content\Models\Page;
use Modules\Content\Schemas\Page\PageSchema;

class UpdatePageAction
{
    public function handle(string $id, array $data): Page
    {
        $record = Page::query()->findOrFail($id);
        $body = [
            PageSchema::STATUS => $data[PageSchema::STATUS],
        ];
        $record->update($body);

        $record->translations()->delete();
        $translations = $data[PageSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        return $record->load(PageSchema::RES_TRANSLATIONS);
    }
}
