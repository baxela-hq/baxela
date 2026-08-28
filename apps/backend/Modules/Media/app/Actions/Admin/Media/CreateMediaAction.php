<?php

namespace Modules\Media\Actions\Admin\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Events\Media\MediaCreatedEvent;
use Modules\Core\Utils\Auth;
use Modules\Media\Dtos\Admin\CreateMediaInput;
use Modules\Media\Exceptions\Admin\Media\CreationFailedException;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Media\MediaSchema;

class CreateMediaAction extends AbstractMediaAction
{
    /**
     * @throws CreationFailedException
     */
    public function handle(CreateMediaInput $input): Model
    {
        $folderId = $input->{MediaSchema::FOLDER_ID};

        if ($folderId !== null) {
            Folder::query()->findOrFail((int) $folderId);
        }

        try {
            DB::beginTransaction();

            $file = $input->{MediaSchema::REQ_FILE};
            $upload = $file->store();

            $record = $this->model->query()->create([
                MediaSchema::USER_ID => Auth::id(),
                MediaSchema::FOLDER_ID => $input->{MediaSchema::FOLDER_ID},
                MediaSchema::DISK => config('filesystems.default'),
                MediaSchema::PATH => $upload,
                MediaSchema::NAME => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                MediaSchema::FILENAME => $file->hashName(),
                MediaSchema::EXTENSION => $file->extension(),
                MediaSchema::MIME_TYPE => $file->getMimeType(),
                MediaSchema::SIZE => $file->getSize(),
            ]);
            $record = $record->refresh();

            DB::commit();

            event(MediaCreatedEvent::fill($record->toArray()));

            return $record;

        } catch (\Throwable $t) {
            DB::rollBack();
            report($t);
            throw new CreationFailedException(previous: $t);
        }
    }
}
