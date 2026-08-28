<?php

namespace Modules\Setting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Setting\Database\Factories\TranslationFactory;
use Modules\Setting\Schemas\Translation\TranslationSchema;

class Translation extends Model
{
    use HasFactory;

    protected $table = TranslationSchema::TABLE;

    protected $fillable = [
        TranslationSchema::SETTING_ID,
        TranslationSchema::LANGUAGE_ID,
        TranslationSchema::VALUE,
    ];

    protected static function newFactory(): TranslationFactory
    {
        return TranslationFactory::new();
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }
}
