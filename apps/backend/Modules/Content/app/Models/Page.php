<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Content\Database\Factories\PageFactory;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageStatusEnum;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property PageStatusEnum $status
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, PageTranslation> $translations
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use TenantScopedTrait;

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

    /** @return HasMany<PageTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
