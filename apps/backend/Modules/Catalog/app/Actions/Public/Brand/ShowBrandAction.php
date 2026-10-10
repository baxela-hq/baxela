<?php

namespace Modules\Catalog\Actions\Public\Brand;

use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema as BTSchema;

class ShowBrandAction extends AbstractBrandAction
{
    /**
     * Resolves the brand by numeric id or by translation slug — the
     * storefront links brands by slug. Numeric ids keep working for API
     * consumers.
     */
    public function handle(string $idOrSlug)
    {
        $query = $this->model->with(BrandSchema::RES_TRANSLATIONS);

        if (ctype_digit($idOrSlug)) {
            return $query->findOrFail($idOrSlug);
        }

        return $query
            ->whereHas(
                BrandSchema::RES_TRANSLATIONS,
                fn ($translation) => $translation->where(BTSchema::SLUG, $idOrSlug)
            )
            ->firstOrFail();
    }
}
