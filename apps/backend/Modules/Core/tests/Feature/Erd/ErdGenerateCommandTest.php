<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\Erd\ModelRelationsScanner;
use Modules\Core\Support\Erd\ModuleTableResolver;

/**
 * Fresh output directory for one test run (unique, /tmp-ephemeral).
 */
function erdfxPath(): string
{
    return sys_get_temp_dir().'/erd-docs-'.uniqid();
}

/**
 * Binds the scanner/resolver to the fixture module and creates the sqlite
 * schema. Uses file-level helpers instead of Pest $this bindings to keep
 * Larastan clean (the TestCall property errors live in the baseline).
 */
function erdfxSetUp(string $path): void
{
    File::deleteDirectory($path);

    $fixtureRoot = dirname(__DIR__, 2).'/Fixtures/Erd';

    app()->bind(ModelRelationsScanner::class, fn () => new ModelRelationsScanner($fixtureRoot));
    app()->bind(ModuleTableResolver::class, fn () => new ModuleTableResolver($fixtureRoot));

    Schema::create('erdfx_posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });

    Schema::create('erdfx_details', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('post_id')->constrained('erdfx_posts')->onDelete('cascade');
        $table->string('slug')->unique();
    });

    // Cross-module style: plain column, no FK — recovered from the relation.
    Schema::create('erdfx_notes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('post_id')->index();
        $table->text('note');
    });

    // Unique column without FK: dashed one-to-one.
    Schema::create('erdfx_summaries', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('post_id')->unique();
    });

    Schema::create('erdfx_tags', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });

    // Model-less pivot: attributed to Shop via the DB_PREFIX fallback.
    Schema::create('erdfx_post_tag', function (Blueprint $table): void {
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('tag_id');
        $table->primary(['post_id', 'tag_id']);
    });

    // Model-less table with a loose reference column.
    Schema::create('erdfx_loose', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('owner_id');
    });

    // Unprefixed table: belongs to "other".
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
}

/**
 * @param  array<string, mixed>  $with
 */
function erdfxGenerate(string $path, array $with = []): int
{
    return Artisan::call('erd:generate', ['--path' => $path, ...$with]);
}

function erdfxRead(string $path, string $file): string
{
    return (string) file_get_contents($path.'/'.$file);
}

it('generates module pages with solid fk, dashed app-level and one-to-one edges', function () {
    $path = erdfxPath();
    erdfxSetUp($path);

    expect(erdfxGenerate($path))->toBe(0);

    $shop = erdfxRead($path, 'shop.md');

    expect($shop)
        ->toContain('erdfx_details }o--|| erdfx_posts : "post_id"')
        ->toContain('erdfx_notes }o..|| erdfx_posts : "post_id"')
        ->toContain('erdfx_summaries ||..o| erdfx_posts : "post_id"')
        ->toContain('erdfx_post_tag }o..|| erdfx_posts : "post_id"')
        ->toContain('erdfx_post_tag }o..|| erdfx_tags : "tag_id"')
        ->toContain('varchar slug UK')
        ->toContain('- `erdfx_loose.owner_id`');
});

it('writes the index and an other page for unprefixed tables', function () {
    $path = erdfxPath();
    erdfxSetUp($path);
    erdfxGenerate($path);

    expect(is_file($path.'/index.md'))->toBeTrue()
        ->and(is_file($path.'/shop.md'))->toBeTrue()
        ->and(is_file($path.'/other.md'))->toBeTrue()
        ->and(erdfxRead($path, 'other.md'))->toContain('users {')
        ->and(erdfxRead($path, 'index.md'))->toContain('| [Shop](shop.md) | 7 | 1 | 4 | 1 |');
});

it('passes check when docs are current and fails when they are stale', function () {
    $path = erdfxPath();
    erdfxSetUp($path);

    expect(erdfxGenerate($path))->toBe(0)
        ->and(erdfxGenerate($path, ['--check' => true]))->toBe(0);

    File::append($path.'/shop.md', "\nstale\n");

    expect(erdfxGenerate($path, ['--check' => true]))->toBe(1);

    // Module-scoped regeneration restores only that page.
    expect(erdfxGenerate($path, ['--module' => 'shop']))->toBe(0)
        ->and(erdfxGenerate($path, ['--check' => true]))->toBe(0);
});

it('rejects an unknown module and lists the valid ones', function () {
    $path = erdfxPath();
    erdfxSetUp($path);

    expect(erdfxGenerate($path, ['--module' => 'nope']))->toBe(1)
        ->and(Artisan::output())->toContain('Unknown module [nope]');
});
