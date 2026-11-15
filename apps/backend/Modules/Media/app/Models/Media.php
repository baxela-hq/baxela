<?php

namespace Modules\Media\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Media\Database\Factories\MediaFactory;
use Modules\Media\Schemas\Media\MediaDiskEnum;
use Modules\Media\Schemas\Media\MediaMimeTypeEnum;
use Modules\Media\Schemas\Media\MediaSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $folder_id
 * @property MediaDiskEnum|null $disk
 * @property string $path
 * @property string $name
 * @property string $filename
 * @property string|null $extension
 * @property MediaMimeTypeEnum $mime_type
 * @property int $size
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Folder|null $folder
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = MediaSchema::TABLE;

    protected $fillable = [
        MediaSchema::USER_ID,
        MediaSchema::FOLDER_ID,
        MediaSchema::DISK,
        MediaSchema::PATH,
        MediaSchema::NAME,
        MediaSchema::FILENAME,
        MediaSchema::EXTENSION,
        MediaSchema::MIME_TYPE,
        MediaSchema::SIZE,
        MediaSchema::METADATA,
    ];

    protected function casts(): array
    {
        return [
            MediaSchema::DISK => MediaDiskEnum::class,
            MediaSchema::MIME_TYPE => MediaMimeTypeEnum::class,
            MediaSchema::METADATA => 'array',
        ];
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }

    /** @return BelongsTo<Folder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
