<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\BrandTranslation;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema as BTSchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Module;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class BrandSeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    private MediaGatewayInterface $mediaGateway;

    private int $brandFolderId;

    /** @var array<string, int|null> */
    private array $languageIds = [];

    /** @var list<int> featured brand ids in seeding order */
    private array $featuredIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        $this->mediaGateway = App::make(MediaGatewayInterface::class);
        $moduleKey = Module::NAME_LOWER.'::seeder.brands';

        $brandFolderId = $this->mediaGateway->getFolderIdByPath('Catalog/Brand');
        if ($brandFolderId === null) {
            $this->command->error('Folder Catalog/Brand not found.');
            $this->command->error('Run this seeder after running the media seeder.');

            return;
        }
        $this->brandFolderId = $brandFolderId;

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $data = [];
        foreach ($langs as $lang) {
            $data[$lang] = Lang::get($moduleKey, [], $lang) ?? [];
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $brands = $data[$masterLang];

        BrandTranslation::query()->delete();
        Brand::query()->delete();
        FeaturedItem::query()
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_BRAND)
            ->delete();

        $this->featuredIds = [];

        $i = 1;
        foreach ($brands as $slug => $brand) {
            $image = $this->logoFor((string) $slug);

            $record = Brand::query()->create([
                BrandSchema::POSITION => $i,
                BrandSchema::IMAGE_MEDIA_ID => $image?->id,
                BrandSchema::IMAGE_URL => $image?->url,
            ]);
            $brandId = $record->{BrandSchema::ID};

            if ($brand['featured'] ?? false) {
                $this->featuredIds[] = $brandId;
            }

            foreach ($langs as $lang) {
                foreach ($data[$lang][$slug]['translations'] ?? [] as $translation) {
                    BrandTranslation::query()->create([
                        BTSchema::BRAND_ID => $brandId,
                        BTSchema::LANGUAGE_ID => $this->languageId($lang),
                        BTSchema::TITLE => $translation[BTSchema::TITLE],
                        BTSchema::SLUG => $translation[BTSchema::SLUG] ?? $slug,
                        BTSchema::DESCRIPTION => $translation[BTSchema::DESCRIPTION] ?? null,
                    ]);
                }
            }
            $i++;
        }

        $this->seedFeaturedItems();
    }

    /**
     * Turn the collected featured ids into positioned featured items.
     */
    private function seedFeaturedItems(): void
    {
        $now = now();

        $rows = collect($this->featuredIds)->values()->map(fn ($id, $index) => [
            FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_BRAND,
            FeaturedItemSchema::FEATUREDABLE_ID => $id,
            FeaturedItemSchema::POSITION => $index + 1,
            FeaturedItemSchema::CREATED_AT => $now,
            FeaturedItemSchema::UPDATED_AT => $now,
        ])->all();

        if ($rows !== []) {
            FeaturedItem::query()->insert($rows);
        }
    }

    /**
     * Publish the brand's seeded logo on the public disk and return its
     * media record. Logos live next to this seeder, keyed by brand slug —
     * so seeding stays reproducible without network access.
     */
    private function logoFor(string $slug): ?object
    {
        $source = __DIR__.'/media/brands/'.$slug.'.png';

        if (! file_exists($source)) {
            return null;
        }

        return $this->mediaGateway->upsertLocal(
            $source,
            'catalog/brands/'.basename($source),
            $this->brandFolderId,
        );
    }

    private function languageId(string $code): ?int
    {
        if (! array_key_exists($code, $this->languageIds)) {
            $this->languageIds[$code] = $this->coreGateway->getLanguageIdByCode($code);
        }

        return $this->languageIds[$code];
    }
}
