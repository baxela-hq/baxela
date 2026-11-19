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

    /** @var bool */
    public $afterCommit = true;

    public function __construct(private readonly ProductIndexWriter $writer) {}

    public function handle(ProductDeletedEvent $event): void
    {
        if (! $this->writer->configured()) {
            return;
        }

        $this->writer->write($event->id);
    }
}
