<?php

namespace Modules\Menu\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Models\MenuLinkTranslation;
use Modules\Menu\Models\MenuTranslation;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema as MTSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema as MLTSchema;
use Modules\Menu\Schemas\Module;

class MenuDatabaseSeeder extends Seeder
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
        $moduleKey = Module::NAME_LOWER.'::seeder';

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();
        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');

        $data = [];
        foreach ($langs as $lang) {
            $data[$lang] = Lang::get($moduleKey, [], $lang) ?? [];
        }

        MenuLinkTranslation::query()->delete();
        MenuTranslation::query()->delete();
        MenuLink::query()->delete();
        Menu::query()->delete();

        foreach (array_keys($data[$masterLang] ?? []) as $location) {
            $this->seedMenu($location, $data, $langs, $masterLang);
        }
    }

    /**
     * @param  array<string, array>  $data  lang => seeder payload
     * @param  array<int, string>  $langs
     */
    private function seedMenu(string $location, array $data, array $langs, string $masterLang): void
    {
        $menu = Menu::query()->create([
            MenuSchema::LOCATION => $location,
            MenuSchema::IS_ACTIVE => true,
        ]);

        foreach ($langs as $lang) {
            MenuTranslation::query()->create([
                MTSchema::MENU_ID => $menu->{MenuSchema::ID},
                MTSchema::LANGUAGE_ID => $this->languageId($lang),
                MTSchema::TITLE => $data[$lang][$location]['title'] ?? $data[$masterLang][$location]['title'],
                MTSchema::DESCRIPTION => $data[$lang][$location]['description']
                    ?? $data[$masterLang][$location]['description']
                    ?? null,
            ]);
        }

        $flat = [];
        foreach ($langs as $lang) {
            $flat[$lang] = $this->flatten($data[$lang][$location]['links'] ?? []);
        }

        $this->seedLinks(
            $data[$masterLang][$location]['links'] ?? [],
            $flat,
            $langs,
            $menu->{MenuSchema::ID},
            null
        );
    }

    /**
     * @param  array<string, array{url: string, translations: array, children?: array}>  $nodes
     * @param  array<string, array<string, array{url: string, translations: array, children?: array}>>  $flat  lang => key => node
     * @param  array<int, string>  $langs
     */
    private function seedLinks(array $nodes, array $flat, array $langs, int $menuId, ?int $parentId): void
    {
        $i = 1;

        foreach ($nodes as $key => $node) {
            $link = MenuLink::query()->create([
                MenuLinkSchema::MENU_ID => $menuId,
                MenuLinkSchema::PARENT_ID => $parentId,
                MenuLinkSchema::POSITION => $i,
                MenuLinkSchema::URL => $node['url'],
                MenuLinkSchema::TARGET => MenuLinkTargetEnum::SELF->value,
            ]);

            foreach ($langs as $lang) {
                $translation = $flat[$lang][$key]['translations'][0]
                    ?? $node['translations'][0];

                MenuLinkTranslation::query()->create([
                    MLTSchema::MENU_LINK_ID => $link->{MenuLinkSchema::ID},
                    MLTSchema::LANGUAGE_ID => $this->languageId($lang),
                    MLTSchema::TITLE => $translation['title'],
                    MLTSchema::DESCRIPTION => $flat[$lang][$key]['description'] ?? null,
                ]);
            }

            if (count($node['children'] ?? [])) {
                $this->seedLinks($node['children'], $flat, $langs, $menuId, $link->{MenuLinkSchema::ID});
            }
            $i++;
        }
    }

    /**
     * Collapse the nested link tree into a key => node lookup.
     *
     * @param  array<string, array{url: string, translations: array, children?: array}>  $nodes
     * @return array<string, array{url: string, translations: array, children?: array}>
     */
    private function flatten(array $nodes, ?array $carry = null): array
    {
        $carry ??= [];

        foreach ($nodes as $key => $node) {
            $carry[$key] = $node;

            if (isset($node['children'])) {
                $carry = $this->flatten($node['children'], $carry);
            }
        }

        return $carry;
    }

    private function languageId(string $code): ?int
    {
        if (! array_key_exists($code, $this->languageIds)) {
            $this->languageIds[$code] = $this->coreGateway->getLanguageIdByCode($code);
        }

        return $this->languageIds[$code];
    }
}
