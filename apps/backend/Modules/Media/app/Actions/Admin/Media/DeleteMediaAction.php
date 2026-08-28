<?php

namespace Modules\Media\Actions\Admin\Media;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Events\Media\MediaDeletedEvent;
use Modules\Media\Schemas\Media\MediaSchema;

class DeleteMediaAction extends AbstractMediaAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);

        try {
            Storage::delete($record->{MediaSchema::PATH});

            $delete = $record->delete();
            if ($delete) {
                event(MediaDeletedEvent::fill($record->toArray()));
            }

            return $delete;
        } catch (\Throwable $t) {
            report($t);

            return false;
        }
    }
}
