<?php

namespace Modules\Media\Actions\Admin\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Events\Media\MediaCreatedEvent;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantWriteStamp;
use Modules\Core\Utils\Auth;
use Modules\Media\Dtos\Admin\CreateMediaInput;
use Modules\Media\Exceptions\Admin\Media\CreationFailedException;
use Modules\Media\Models\Folder;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Media\Schemas\MediaOutbox\MediaOutboxSchema;
use Modules\Media\Support\MediaPublicationProcessor;
use Throwable;

class CreateMediaAction extends AbstractMediaAction
{
    /**
     * Stage the bytes, then commit the media row and its publication
     * outbox entry as ONE unit (postgres-migration §3.3): the durable
     * record IS the delivery guarantee — a crash between commit and the
     * inline publication attempt leaves a pending outbox entry the
     * scheduled processor recovers. The final key is content-addressed
     * (immutable): identical bytes ⇒ identical key, different content ⇒
     * different key — publication races are no-ops by construction.
     *
     * @throws CreationFailedException
     */
    public function handle(CreateMediaInput $input): Media
    {
        $folderId = $input->{MediaSchema::FOLDER_ID};

        if ($folderId !== null) {
            // uploads land only in folders owned by the uploader
            Folder::query()
                ->where(FolderSchema::USER_ID, Auth::id())
                ->findOrFail((int) $folderId);
        }

        $disk = config('filesystems.default');

        try {
            $file = $input->{MediaSchema::REQ_FILE};
            $contents = $file->getContent();
            $hash = hash('sha256', $contents);

            // Same-filesystem staging sibling (the publication's atomic
            // rename locality); the sweep cleans abandoned staging.
            $stagingPath = '.staging/media/'.Str::uuid()->toString().'.'.$file->extension();
            Storage::disk($disk)->put($stagingPath, $contents);

            // Immutable content-addressed final key.
            $finalKey = 'media/'.substr($hash, 0, 2).'/'.$hash.'.'.$file->extension();

            DB::beginTransaction();

            $record = $this->model->query()->create([
                MediaSchema::USER_ID => Auth::id(),
                MediaSchema::FOLDER_ID => $input->{MediaSchema::FOLDER_ID},
                MediaSchema::DISK => $disk,
                MediaSchema::PATH => $finalKey,
                MediaSchema::NAME => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                MediaSchema::FILENAME => $file->hashName(),
                MediaSchema::EXTENSION => $file->extension(),
                MediaSchema::MIME_TYPE => $file->getMimeType(),
                MediaSchema::SIZE => $file->getSize(),
            ]);

            DB::table(MediaOutboxSchema::TABLE)->insert([
                TenantColumn::TENANT_ID => TenantWriteStamp::tenantIdForWrite(),
                MediaOutboxSchema::MEDIA_ID => $record->getKey(),
                MediaOutboxSchema::EXPECTED_HASH => $hash,
                MediaOutboxSchema::STAGING_PATH => $stagingPath,
                MediaOutboxSchema::FINAL_KEY => $finalKey,
                MediaOutboxSchema::STATUS => MediaOutboxSchema::STATUS_PENDING,
            ]);

            DB::commit();
        } catch (Throwable $t) {
            DB::rollBack();
            report($t);
            throw new CreationFailedException(previous: $t);
        }

        // Latency optimization only — the outbox row is the durability;
        // the scheduled processor is the recovery path (crash-window safe).
        try {
            app(MediaPublicationProcessor::class)->process(1);
        } catch (Throwable) {
            // Never fail the creation for a deferred publication.
        }

        event(MediaCreatedEvent::fill($record->refresh()->toArray()));

        return $record->refresh();
    }
}
