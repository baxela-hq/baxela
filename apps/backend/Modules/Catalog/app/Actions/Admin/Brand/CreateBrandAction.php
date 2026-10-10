<?php

namespace Modules\Catalog\Actions\Admin\Brand;

use Modules\Catalog\Models\Brand;
use Modules\Catalog\Schemas\Brand\BrandSchema;

class CreateBrandAction
{
    public function handle(array $data): Brand
    {
        $body = [
            BrandSchema::POSITION => $data[BrandSchema::POSITION],
            BrandSchema::IMAGE_MEDIA_ID => $data[BrandSchema::IMAGE_MEDIA_ID] ?? null,
            BrandSchema::IMAGE_URL => $data[BrandSchema::IMAGE_URL] ?? null,
        ];
        $record = Brand::query()->create($body);

        $translations = $data[BrandSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        $record = $record->refresh();

        return $record->load(BrandSchema::RES_TRANSLATIONS);
    }
}
