<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Media\Database\Factories\FolderFactory;
use Modules\Media\Schemas\Folder\FolderSchema;

class Folder extends Model
{
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

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }
}
