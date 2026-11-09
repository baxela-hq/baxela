<?php

namespace App;

class Baxela
{
    /**
     * The platform version, and the single value extension manifests are
     * compatibility-checked against (extension `compat.core` constraints
     * must satisfy it — see Modules\Core\Extensions\ExtensionManager).
     * Bump per release; the release process owns this constant.
     */
    public const string VERSION = '0.1.0';
}
