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

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        //        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();
        $langs = ['en'];
        $moduleKey = Module::NAME_LOWER.'::seeder.categories';

        foreach ($langs as $lang) {

            $categories = Lang::get($moduleKey, [], $lang);

            CategoryTranslation::query()->delete();
            Category::query()->delete();

            $this->generate($categories);
        }

    }

    /**
     * @param  array{title: string, slug: string, children: array}  $categories
     */
    private function generate(array $categories, ?int $parentId = null): void
    {
        $languageId = $this->coreGateway->getLanguageIdByCode(App::currentLocale());

        if (is_null($languageId)) {
            return;
        }

        /* @var array{title: string, slug: string, children: array} $category */
        $i = 1;
        foreach ($categories as $category) {
            $catModel = Category::query()->create([
                CategorySchema::PARENT_ID => $parentId,
                CategorySchema::POSITION => $i,
            ]);
            $id = $catModel->{CategorySchema::ID};
            CategoryTranslation::query()->create([
                CTSchema::CATEGORY_ID => $id,
                CTSchema::LANGUAGE_ID => $languageId,
                CTSchema::TITLE => $category[CTSchema::TITLE],
                CTSchema::SLUG => $category[CTSchema::SLUG],
            ]);
            if (count($category['children'])) {
                $this->generate($category['children'], $id);
            }
            $i++;
        }
    }
}
