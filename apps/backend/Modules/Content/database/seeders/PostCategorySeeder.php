<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Content\Actions\Admin\PostCategory\CreatePostCategoryAction;
use Modules\Content\Models\PostCategory;
use Modules\Content\Models\PostCategoryTranslation;
use Modules\Content\Schemas\Module;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class PostCategorySeeder extends Seeder
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

        $languageId = $this->coreGateway->getLanguageIdByCode(App::currentLocale());
        if (! $languageId) {
            $this->command->error('Language not found for default language: '.App::currentLocale());
            $this->command->error('Run this seeder after running the core seeder.');

            return;
        }

        $moduleKey = Module::NAME_LOWER.'::seeder.post_categories';

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $data = [];
        foreach ($langs as $lang) {
            $data[$lang] = Lang::get($moduleKey, [], $lang) ?? [];
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $categories = $data[$masterLang];

        PostCategoryTranslation::query()->delete();
        PostCategory::query()->delete();

        foreach ($categories as $slug => $category) {
            $translations = [];

            foreach ($langs as $lang) {
                foreach ($data[$lang][$slug]['translations'] ?? [] as $translation) {
                    $translations[] = [
                        PCTSchema::LANGUAGE_ID => $this->languageId($lang),
                        PCTSchema::TITLE => $translation[PCTSchema::TITLE],
                        PCTSchema::SLUG => $translation[PCTSchema::SLUG] ?? $slug,
                        PCTSchema::DESCRIPTION => $translation[PCTSchema::DESCRIPTION] ?? null,
                    ];
                }
            }

            app(CreatePostCategoryAction::class)->handle([
                PostCategorySchema::PARENT_ID => null,
                PostCategorySchema::POSITION => null,
                PostCategorySchema::RES_TRANSLATIONS => $translations,
            ]);
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
