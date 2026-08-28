<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Media\Database\Factories\MediaFactory;
use Modules\Media\Schemas\Media\MediaDiskEnum;
use Modules\Media\Schemas\Media\MediaMimeTypeEnum;
use Modules\Media\Schemas\Media\MediaSchema;

class Media extends Model
{
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

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
