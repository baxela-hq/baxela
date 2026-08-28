<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Catalog\Database\Factories\AttributeTemplateFactory;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;

class AttributeTemplate extends Model
{
    use HasFactory;

    protected $table = AttributeTemplateSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        AttributeTemplateSchema::TITLE,
        AttributeTemplateSchema::DESCRIPTION,
        AttributeTemplateSchema::IS_ACTIVE,
        AttributeTemplateSchema::POSITION,
    ];

    protected function casts(): array
    {
        return [
            AttributeTemplateSchema::IS_ACTIVE => 'boolean',
        ];
    }

    protected static function newFactory(): AttributeTemplateFactory
    {
        return AttributeTemplateFactory::new();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(AttributeGroup::class, AttributeTemplateGroupSchema::TABLE,
            AttributeTemplateGroupSchema::TEMPLATE_ID, AttributeTemplateGroupSchema::GROUP_ID)
            ->withPivot(AttributeTemplateGroupSchema::POSITION)
            ->orderByPivot(AttributeTemplateGroupSchema::POSITION);
    }
}
