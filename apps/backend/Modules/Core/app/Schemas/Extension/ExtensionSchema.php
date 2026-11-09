<?php

namespace Modules\Core\Schemas\Extension;

use Modules\Core\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ExtensionSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'extensions';

    /** The composer package name (vendor/baxela-slug) — the extension's identity. */
    public const string SLUG = 'slug';

    public const string ENABLED = 'enabled';

    /**
     * Computed wire fields (no backing column): the version is read live
     * from the installed packages, the skip reason from the boot guard —
     * neither is stored, so neither can drift from Composer or the
     * runtime decision.
     */
    public const string RES_VERSION = 'version';

    public const string RES_INSTALLED = 'installed';

    public const string RES_SKIP_REASON = 'skip_reason';

    /** Package-name pattern a baxela:install accepts (marketplace doc §3). */
    public const string SLUG_PATTERN = '^[a-z0-9][a-z0-9_.-]*/baxela-[a-z0-9][a-z0-9_.-]*$';
}
