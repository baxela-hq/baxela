<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Catalog\Schemas\Module;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class CategorySeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    /** @var array<string, int|null> */
    private array $languageIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        $moduleKey = Module::NAME_LOWER.'::seeder.categories';

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

        $this->generate($categories, $data, $langs, null);
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
     * @param  array<string, array{translations: array, children?: array}>  $nodes
     * @param  array<string, array<string, array{translations: array, children?: array}>>  $data
     * @param  array<int, string>  $langs
     */
    private function generate(array $nodes, array $data, array $langs, ?int $parentId): void
    {
        $i = 1;

        foreach ($nodes as $slug => $node) {
            $category = Category::query()->create([
                CategorySchema::PARENT_ID => $parentId,
                CategorySchema::POSITION => $i,
            ]);
            $categoryId = $category->{CategorySchema::ID};

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

    private function languageId(string $code): ?int
    {
        if (! array_key_exists($code, $this->languageIds)) {
            $this->languageIds[$code] = $this->coreGateway->getLanguageIdByCode($code);
        }

        return $this->languageIds[$code];
    }
}
