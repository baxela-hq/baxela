<?php

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shipping\Database\Factories\MethodTranslationFactory;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;

class MethodTranslation extends Model
{
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

    public function method(): BelongsTo
    {
        return $this->belongsTo(Method::class);
    }
}
