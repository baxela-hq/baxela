<?php

namespace Modules\Core\Support\Tenancy;

/**
 * A registered cross-store operation (multi-tenancy §6.5). The registry
 * is keyed by the EXACT operation class; id() is a label for display and
 * audit only — a different class returning the same label obtains nothing.
 *
 * The object carries the operation's validated arguments. Operations are
 * final classes, and nothing here is user-chosen: the caller constructs a
 * concrete operation in code.
 */
interface CentralOperation
{
    public function id(): string;
}
