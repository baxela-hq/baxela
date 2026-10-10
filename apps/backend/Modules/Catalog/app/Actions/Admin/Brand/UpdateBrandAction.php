<?php

namespace Modules\Catalog\Actions\Admin\Brand;

use Modules\Catalog\Models\Brand;
use Modules\Catalog\Schemas\Brand\BrandSchema;

class UpdateBrandAction
{
    public function handle(string $id, array $data): Brand
    {
        $record = Brand::query()->findOrFail($id);
        $body = [
            BrandSchema::POSITION => $data[BrandSchema::POSITION],
            BrandSchema::IMAGE_MEDIA_ID => $data[BrandSchema::IMAGE_MEDIA_ID] ?? null,
            BrandSchema::IMAGE_URL => $data[BrandSchema::IMAGE_URL] ?? null,
        ];
        $record->update($body);

        $record->translations()->delete();
        $translations = $data[BrandSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        return $record->load(BrandSchema::RES_TRANSLATIONS);
    }
}
