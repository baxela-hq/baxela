<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Content\Actions\Admin\Page\CreatePageAction;
use Modules\Content\Models\Page;
use Modules\Content\Models\PageTranslation;
use Modules\Content\Schemas\Module;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Content\Schemas\Page\PageTranslationSchema as PTSchema;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class PageSeeder extends Seeder
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

        $moduleKey = Module::NAME_LOWER.'::seeder.pages';

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $data = [];
        foreach ($langs as $lang) {
            $data[$lang] = Lang::get($moduleKey, [], $lang) ?? [];
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $pages = $data[$masterLang];

        Model::reguard();

        PageTranslation::query()->delete();
        Page::query()->delete();

        $this->generate($pages, $data, $langs);
    }

    /**
     * @param  array<string, array{translations: array}>  $pages
     * @param  array<string, array<string, array{translations: array}>>  $data
     * @param  array<int, string>  $langs
     */
    private function generate(array $pages, array $data, array $langs): void
    {
        foreach ($pages as $slug => $page) {
            $translations = [];

            foreach ($langs as $lang) {
                foreach ($data[$lang][$slug]['translations'] ?? [] as $translation) {
                    $translations[] = [
                        PTSchema::LANGUAGE_ID => $this->languageId($lang),
                        PTSchema::TITLE => $translation[PTSchema::TITLE],
                        PTSchema::SLUG => $translation[PTSchema::SLUG] ?? $slug,
                        PTSchema::CONTENT => $translation[PTSchema::CONTENT],
                        PTSchema::DESCRIPTION => $translation[PTSchema::DESCRIPTION] ?? null,
                    ];
                }
            }

            $payload = [
                PageSchema::STATUS => PageStatusEnum::PUBLISHED,
                PageSchema::RES_TRANSLATIONS => $translations,
            ];

            app(CreatePageAction::class)->handle($payload);
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
