<?php

namespace Modules\Content\Actions\Public\Page;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema as PTSchema;

class ShowPageAction extends AbstractPageAction
{
    /**
     * Resolves a published page by its translation slug in any language.
     */
    public function handle(string $slug): Model
    {
        return $this->model
            ->where(PageSchema::STATUS, PageStatusEnum::PUBLISHED)
            ->with(PageSchema::RES_TRANSLATIONS)
            ->whereHas(
                PageSchema::RES_TRANSLATIONS,
                fn ($translation) => $translation->where(PTSchema::SLUG, $slug)
            )
            ->firstOrFail();
    }
}
