<?php

namespace Modules\Media\Listeners\Media;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Core\Contracts\Events\Media\MediaCreatedEvent;
use Modules\Media\Actions\Admin\Media\GenerateMediaVariantsAction;
use Modules\Media\Models\Media;

/**
 * Queued variant generation (gap-C3 D4): fires after an upload lands.
 * The hard timeout is always shorter than the per-media lock TTL
 * (config: media.images.*) so a live worker can never be lock-expired.
 * A "locked" result re-releases the job with a short delay instead of
 * burning an attempt.
 */
class GenerateImageVariantsListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(protected GenerateMediaVariantsAction $action) {}

    /**
     * Handle the event.
     */
    public function handle(MediaCreatedEvent $event): void
    {
        $media = Media::query()->find($event->id);

        if (is_null($media)) {
            return;
        }

        $result = $this->action->handle($media);

        if ($result === 'locked') {
            $this->release(60);
        }
    }
}
