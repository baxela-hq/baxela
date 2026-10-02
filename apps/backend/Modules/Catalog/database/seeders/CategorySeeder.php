<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Schemas\Module;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class CategorySeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    private MediaGatewayInterface $mediaGateway;

    private int $categoryFolderId;

    /** @var array<string, int|null> */
    private array $languageIds = [];

    /** @var list<int> featured category ids in seeding order */
    private array $featuredIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        $this->mediaGateway = App::make(MediaGatewayInterface::class);
        $moduleKey = Module::NAME_LOWER.'::seeder.categories';

        $categoryFolderId = $this->mediaGateway->getFolderIdByPath('Catalog/Category');
        if ($categoryFolderId === null) {
            $this->command->error('Folder Catalog/Category not found.');
            $this->command->error('Run this seeder after running the media seeder.');

            return;
        }
        $this->categoryFolderId = $categoryFolderId;

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $masterTree = [];
        $data = [];
        foreach ($langs as $lang) {
            $rows = Lang::get($moduleKey, [], $lang) ?? [];
            $masterTree[$lang] = $rows;
            $data[$lang] = $this->flatten($rows);
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $categories = $masterTree[$masterLang];

        CategoryTranslation::query()->delete();
        Category::query()->delete();
        FeaturedItem::query()
            ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_CATEGORY)
            ->delete();

        $this->featuredIds = [];
        $this->generate($categories, $data, $langs, null);
        $this->seedFeaturedItems();
    }

    /**
     * Turn the collected featured ids into positioned featured items.
     */
    private function seedFeaturedItems(): void
    {
        $now = now();

        $rows = collect($this->featuredIds)->values()->map(fn ($id, $index) => [
            FeaturedItemSchema::FEATUREDABLE_TYPE => FeaturedItemSchema::TYPE_CATEGORY,
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
     * Collapse the nested category tree into a slug => translations lookup.
     *
     * @param  array<string, array{translations: array, children?: array}>  $nodes
     * @return array<string, array<string, array{translations: array, children?: array}>>
     */
    private function flatten(array $nodes, ?array $carry = null): array
    {
        $carry ??= [];

        foreach ($nodes as $slug => $node) {
            $carry[$slug] = $node;

            if (isset($node['children'])) {
                $carry = $this->flatten($node['children'], $carry);
            }
        }

        return $carry;
    }

    /**
     * @param  array<string, array{translations: array, children?: array, featured?: bool}>  $nodes
     * @param  array<string, array<string, array{translations: array, children?: array}>>  $data
     * @param  array<int, string>  $langs
     */
    private function generate(array $nodes, array $data, array $langs, ?int $parentId): void
    {
        $i = 1;

        foreach ($nodes as $slug => $node) {
            $image = $this->imageFor((string) $slug);

            $category = Category::query()->create([
                CategorySchema::PARENT_ID => $parentId,
                CategorySchema::POSITION => $i,
                CategorySchema::IMAGE_MEDIA_ID => $image?->id,
                CategorySchema::IMAGE_URL => $image?->url,
            ]);
            $categoryId = $category->{CategorySchema::ID};

            if ($node['featured'] ?? false) {
                $this->featuredIds[] = $categoryId;
            }

            foreach ($langs as $lang) {
                foreach ($data[$lang][$slug]['translations'] ?? [] as $translation) {
                    CategoryTranslation::query()->create([
                        CTSchema::CATEGORY_ID => $categoryId,
                        CTSchema::LANGUAGE_ID => $this->languageId($lang),
                        CTSchema::TITLE => $translation[CTSchema::TITLE],
                        CTSchema::SLUG => $translation[CTSchema::SLUG] ?? $slug,
                        CTSchema::DESCRIPTION => $translation[CTSchema::DESCRIPTION] ?? null,
                    ]);
                }
            }

            if (count($node['children'] ?? [])) {
                $this->generate($node['children'], $data, $langs, $categoryId);
            }
            $i++;
        }
    }

    /**
     * Publish the category's seeded cover photo on the public disk and
     * return its media record. Photos live next to this seeder, keyed by
     * category slug — so seeding stays reproducible without network access.
     */
    private function imageFor(string $slug): ?object
    {
        $source = __DIR__.'/media/categories/'.$slug.'.jpg';

        if (! file_exists($source)) {
            return null;
        }

        return $this->mediaGateway->upsertLocal(
            $source,
            'catalog/categories/'.basename($source),
            $this->categoryFolderId,
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
