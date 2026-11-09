<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Schemas\Extension\ExtensionSchema;

/**
 * @property string $slug
 * @property bool $enabled
 */
class Extension extends Model
{
    protected $table = ExtensionSchema::TABLE;

    protected $fillable = [
        ExtensionSchema::SLUG,
        ExtensionSchema::ENABLED,
    ];

    protected function casts(): array
    {
        return [
            ExtensionSchema::ENABLED => 'boolean',
        ];
    }
}
