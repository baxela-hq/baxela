<?php

namespace Modules\Media\Console\Commands;

use Illuminate\Console\Command;
use Modules\Media\Actions\Admin\Media\GenerateMediaVariantsAction;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;

class RegenerateMediaVariantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:regenerate-variants
        {--id= : Regenerate a single media record}
        {--all : Regenerate every image media record}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate image variants (gap-C3 D8) — for preset/config changes; never required after plain uploads';

    /**
     * Execute the console command.
     */
    public function handle(GenerateMediaVariantsAction $action): int
    {
        $id = $this->option('id');

        if ($id !== null && $this->option('all')) {
            $this->error('Use --id or --all, not both.');

            return self::FAILURE;
        }

        if ($id !== null) {
            $media = Media::query()->find((int) $id);

            if (is_null($media)) {
                $this->error("Media {$id} not found.");

                return self::FAILURE;
            }

            return $this->regenerate($action, $media);
        }

        if ($this->option('all')) {
            Media::query()
                ->whereIn(MediaSchema::MIME_TYPE, (array) config('media.images.formats'))
                ->chunkById(100, function ($media) use ($action): void {
                    foreach ($media as $record) {
                        $this->regenerate($action, $record);
                    }
                });

            return self::SUCCESS;
        }

        $this->error('Provide --id or --all.');

        return self::FAILURE;
    }

    private function regenerate(GenerateMediaVariantsAction $action, Media $media): int
    {
        $result = $action->handle($media, force: true);

        $this->line("#{$media->getKey()}: {$result}");

        return in_array($result, ['generated', 'skipped', 'no-op'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
