<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;

class FeaturedItem extends Model
{
    protected $table = FeaturedItemSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        FeaturedItemSchema::FEATUREDABLE_TYPE,
        FeaturedItemSchema::FEATUREDABLE_ID,
        FeaturedItemSchema::POSITION,
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            FeaturedItemSchema::POSITION => 'integer',
        ];
    }

    public function featuredable(): MorphTo
    {
        return $this->morphTo();
    }
}
