<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Content\Database\Factories\PageFactory;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;

class Page extends Model
{
    use HasFactory;

    protected $table = PageSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PageSchema::STATUS,
    ];

    protected function casts(): array
    {
        return [
            PageSchema::STATUS => PageStatusEnum::class,
        ];
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
