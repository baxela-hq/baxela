<?php

namespace Modules\Catalog\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Catalog\Search\ProductIndexWriter;
use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;

/**
 * Product deletion → index deletion task (queued, afterCommit). The
 * durable journal entry came from the delete action's transaction.
 */
final class DeindexProductOnProductDeleted implements ShouldQueue
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

    public function handle(ProductDeletedEvent $event): void
    {
        if (! $this->writer->configured()) {
            return;
        }

        $this->writer->write($event->id);
    }
}
