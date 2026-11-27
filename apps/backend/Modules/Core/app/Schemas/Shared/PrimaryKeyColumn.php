<?php

namespace Modules\Core\Schemas\Shared;

/**
 * The shared primary-key column, exported as a plain class so generic Core
 * helpers can reference it statically. Trait constants resolve only through
 * the class that uses the trait, so Core code cannot read
 * `PkAndTimestampsTrait::ID` directly (multi-tenancy §4.3).
 */
final class PrimaryKeyColumn
{
    public const string ID = 'id';

    private function __construct() {}
}
