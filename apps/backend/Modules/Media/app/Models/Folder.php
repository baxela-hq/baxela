<?php

namespace Modules\Media\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Media\Database\Factories\FolderFactory;
use Modules\Media\Schemas\Folder\FolderSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $name
 * @property int|null $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, Media> $media
 */
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    protected $table = FolderSchema::TABLE;

    protected $fillable = [
        FolderSchema::USER_ID,
        FolderSchema::PARENT_ID,
        FolderSchema::NAME,
        FolderSchema::POSITION,
    ];

    protected static function newFactory(): FolderFactory
    {
        return FolderFactory::new();
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
