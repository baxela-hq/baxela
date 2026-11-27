<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $attribute_id
 * @property int|null $language_id
 * @property string $title
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AttributeTranslation extends Model
{
    use HasFactory;
    use TenantScopedTrait;

    protected $table = AttributeTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeTranslationSchema::ATTRIBUTE_ID,
        AttributeTranslationSchema::LANGUAGE_ID,
        AttributeTranslationSchema::TITLE,
    ];

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
