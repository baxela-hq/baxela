<?php

namespace Modules\Catalog\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Catalog\Search\ProductIndexWriter;
use Modules\Core\Contracts\Events\Catalog\ProductCreatedEvent;
use Modules\Core\Contracts\Events\Catalog\ProductUpdatedEvent;

/**
 * Incremental search indexing (alpha.3): queued + afterCommit so the
 * Meili submission can never run before the mutation transaction commits
 * (review v9 F2). The durable journal entry was written transactionally
 * by the mutation; this listener only drives the remote write under the
 * writer's lease fencing. Ambiguous/deferred outcomes are covered by the
 * journal retry and the nightly reindex.
 */
final class IndexProductOnProductChanged implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(private readonly ProductIndexWriter $writer)
    {
        // afterCommit via constructor: the Queueable trait owns the
        // property declaration — redeclaring it on the class is a fatal
        // composition error (found by the dispatch-registry test).
        $this->afterCommit = true;
    }

    public function handle(ProductCreatedEvent|ProductUpdatedEvent $event): void
    {
        // Unconfigured engine: the journal entries stay durable and the
        // nightly reindex covers them — running the writer against the
        // Null client would only defer work that the reindex performs.
        if (! $this->writer->configured()) {
            return;
        }

        $this->writer->write($event->id);
    }
}
