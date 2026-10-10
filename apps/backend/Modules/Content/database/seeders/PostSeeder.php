<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Modules\Content\Actions\Admin\Post\CreatePostAction;
use Modules\Content\Models\Post;
use Modules\Content\Models\PostCategoryTranslation;
use Modules\Content\Schemas\Module;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;

class PostSeeder extends Seeder
{
    private CoreGatewayInterface $coreGateway;

    private MediaGatewayInterface $mediaGateway;

    private int $postFolderId;

    /** @var array<string, int|null> */
    private array $languageIds = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->coreGateway = App::make(CoreGatewayInterface::class);
        $this->mediaGateway = App::make(MediaGatewayInterface::class);

        $languageId = $this->coreGateway->getLanguageIdByCode(App::currentLocale());
        if (! $languageId) {
            $this->command->error('Language not found for default language: '.App::currentLocale());
            $this->command->error('Run this seeder after running the core seeder.');

            return;
        }

        $postFolderId = $this->mediaGateway->getFolderIdByPath('Content/Post');
        if ($postFolderId === null) {
            $this->command->error('Folder Content/Post not found.');
            $this->command->error('Run this seeder after running the media seeder.');

            return;
        }
        $this->postFolderId = $postFolderId;

        $moduleKey = Module::NAME_LOWER.'::seeder.posts';

        $langs = $this->coreGateway->getActiveLanguages()->pluck(LanguageSchema::CODE)->toArray();

        $data = [];
        foreach ($langs as $lang) {
            $data[$lang] = Lang::get($moduleKey, [], $lang) ?? [];
        }

        $masterLang = in_array('en', $langs, true) ? 'en' : ($langs[0] ?? 'en');
        $posts = $data[$masterLang];

        Post::query()->delete();

        foreach ($posts as $slug => $post) {
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

            $image = $this->imageFor((string) $slug);

            app(CreatePostAction::class)->handle([
                PostSchema::STATUS => PostStatusEnum::PUBLISHED,
                PostSchema::IMAGE_MEDIA_ID => $image?->id,
                PostSchema::IMAGE_URL => $image?->url,
                PostSchema::RES_CATEGORIES => $this->categoryIds($post['categories'] ?? []),
                PostSchema::RES_TRANSLATIONS => $translations,
            ]);
        }
    }

    /**
     * Publish the post's seeded cover photo on the public disk and return
     * its media record. Photos live next to this seeder, keyed by post
     * slug — so seeding stays reproducible without network access.
     */
    private function imageFor(string $slug): ?object
    {
        $source = __DIR__.'/media/posts/'.$slug.'.jpg';

        if (! file_exists($source)) {
            return null;
        }

        return $this->mediaGateway->upsertLocal(
            $source,
            'content/posts/'.basename($source),
            $this->postFolderId,
        );
    }

    /**
     * Resolve category slugs (master-language translation slugs) to ids;
     * missing categories are skipped so the post still seeds.
     *
     * @param  array<int, string>  $slugs
     * @return array<int, int>
     */
    private function categoryIds(array $slugs): array
    {
        $ids = [];
        foreach ($slugs as $slug) {
            $categoryId = PostCategoryTranslation::query()
                ->where(PCTSchema::SLUG, $slug)
                ->value(PCTSchema::POST_CATEGORY_ID);
            if (! is_null($categoryId)) {
                $ids[] = (int) $categoryId;
            }
        }

        return $ids;
    }

    private function languageId(string $code): ?int
    {
        if (! array_key_exists($code, $this->languageIds)) {
            $this->languageIds[$code] = $this->coreGateway->getLanguageIdByCode($code);
        }

        return $this->languageIds[$code];
    }
}
