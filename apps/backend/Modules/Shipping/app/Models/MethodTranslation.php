<?php

namespace Modules\Shipping\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shipping\Database\Factories\MethodTranslationFactory;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;

/**
 * @property int $id
 * @property int $method_id
 * @property int|null $language_id
 * @property string $name
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Method $method
 */
class MethodTranslation extends Model
{
    /** @use HasFactory<MethodTranslationFactory> */
    use HasFactory;

    protected $table = MethodTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        MethodTranslationSchema::METHOD_ID,
        MethodTranslationSchema::LANGUAGE_ID,
        MethodTranslationSchema::NAME,
        MethodTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): MethodTranslationFactory
    {
        return MethodTranslationFactory::new();
    }

    /** @return BelongsTo<Method, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(Method::class);
    }
}
