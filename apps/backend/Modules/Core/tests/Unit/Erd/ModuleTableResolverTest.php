<?php

use Illuminate\Support\Facades\File;
use Modules\Core\Support\Erd\ModuleTableResolver;

it('resolves tables by model mapping, longest prefix, then other', function () {
    $root = sys_get_temp_dir().'/erd-resolver-'.uniqid();

    File::ensureDirectoryExists($root.'/Alpha/app/Schemas');
    File::ensureDirectoryExists($root.'/AlphaBeta/app/Schemas');
    File::put($root.'/Alpha/app/Schemas/Module.php', "<?php\nnamespace Fixtures;\nclass Module\n{\n    public const string DB_PREFIX = 'alpha_';\n}\n");
    File::put($root.'/AlphaBeta/app/Schemas/Module.php', "<?php\nnamespace Fixtures;\nclass Module\n{\n    public const string DB_PREFIX = 'alpha_beta_';\n}\n");

    $resolved = (new ModuleTableResolver($root))->resolve(
        ['direct_table' => 'Direct'],
        ['direct_table', 'alpha_beta_x', 'alpha_y', 'stray'],
    );

    expect($resolved)->toBe([
        'alpha_beta_x' => 'AlphaBeta',
        'alpha_y' => 'Alpha',
        'direct_table' => 'Direct',
        'stray' => ModuleTableResolver::OTHER,
    ]);

    File::deleteDirectory($root);
});

it('falls back to other when no module prefix matches', function () {
    $root = sys_get_temp_dir().'/erd-resolver-'.uniqid();
    File::ensureDirectoryExists($root.'/Shop/app/Schemas');
    File::put($root.'/Shop/app/Schemas/Module.php', "<?php class Module { public const string DB_PREFIX = 'erdfx_'; }");

    $resolved = (new ModuleTableResolver($root))->resolve([], ['jobs', 'sessions']);

    expect($resolved)->toBe([
        'jobs' => ModuleTableResolver::OTHER,
        'sessions' => ModuleTableResolver::OTHER,
    ]);

    File::deleteDirectory($root);
});
