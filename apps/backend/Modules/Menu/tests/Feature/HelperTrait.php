<?php

namespace Modules\Menu\Tests\Feature;

use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Modules\Auth\Models\User;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\Menu\MenuLocationEnum;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;

trait HelperTrait
{
    public function baseUrl(string $endpoint): string
    {
        return 'api/v1/menu'.$endpoint;
    }


    public function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** A translated header menu with a two-level link tree. */
    public function seedTransferMenu(int $languageId): array
    {
        $menu = Menu::query()->create([
            MenuSchema::LOCATION => MenuLocationEnum::HEADER->value,
            MenuSchema::IS_ACTIVE => true,
        ]);
        $menu->translations()->create(['language_id' => $languageId, 'title' => 'Header menu']);

        $parent = MenuLink::query()->create([
            MenuLinkSchema::MENU_ID => $menu->id,
            MenuLinkSchema::PARENT_ID => null,
            MenuLinkSchema::POSITION => 1,
            MenuLinkSchema::URL => '/shop',
            MenuLinkSchema::TARGET => MenuLinkTargetEnum::SELF->value,
        ]);
        $parent->translations()->create(['language_id' => $languageId, 'title' => 'Shop']);

        $child = MenuLink::query()->create([
            MenuLinkSchema::MENU_ID => $menu->id,
            MenuLinkSchema::PARENT_ID => $parent->id,
            MenuLinkSchema::POSITION => 1,
            MenuLinkSchema::URL => '/shop/new',
            MenuLinkSchema::TARGET => MenuLinkTargetEnum::SELF->value,
        ]);
        $child->translations()->create(['language_id' => $languageId, 'title' => 'New arrivals']);

        return ['menu' => $menu, 'parent' => $parent, 'child' => $child];
    }


    /**
     * Export through the module's own data endpoint and return the
     * decoded envelope.
     *
     * @return array<string, mixed>
     */
    public function exportedMenuDataFile(): array
    {
        return json_decode(
            $this->getJson($this->baseUrl('/admin/data/export'))->assertOk()->streamedContent(),
            true
        );
    }

    /** The multipart file field for the data-import endpoints. */
    public function transferFile(string $content, string $name = 'transfer.json'): File
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
