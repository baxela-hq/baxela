<?php

namespace Modules\Core\Contracts\Events;

/**
 * Marker for events whose audit record is a PRECONDITION of the change
 * they describe, not an afterthought. The audit listener normally
 * swallows its own failures (an audit problem must never break the
 * request it audits); for a marked event it rethrows instead, so when the
 * listener runs inside the change's transaction — as operator overrides
 * do — a missing audit row rolls the change back on every engine.
 */
interface MustBeAudited
{
    // No members: a type-level contract only.
}
