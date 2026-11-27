<?php

namespace Modules\Setting\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Setting\Database\Factories\TranslationFactory;
use Modules\Setting\Schemas\Translation\TranslationSchema;

/**
 * @property int $id
 * @property int $setting_id
 * @property int $language_id
 * @property string|null $value
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    use TenantScopedTrait;

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

    /** @return BelongsTo<Setting, $this> */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }
}
