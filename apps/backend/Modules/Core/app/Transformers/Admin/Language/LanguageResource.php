<?php

namespace Modules\Core\Transformers\Admin\Language;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Schemas\Language\LanguageSchema as Schema;

class LanguageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->{Schema::ID},
            Schema::LOCALE => $this->{Schema::LOCALE},
            Schema::NAME => $this->{Schema::NAME},
            Schema::NATIVE_NAME => $this->{Schema::NATIVE_NAME},
            Schema::CODE => $this->{Schema::CODE},
            Schema::CODE3 => $this->{Schema::CODE3},
            Schema::IS_RTL => $this->{Schema::IS_RTL},
            Schema::IS_ACTIVE => $this->{Schema::IS_ACTIVE},
            Schema::IS_DEFAULT => $this->{Schema::IS_DEFAULT},
            Schema::POSITION => $this->{Schema::POSITION},
            Schema::DATE_FORMAT => $this->{Schema::DATE_FORMAT},
            Schema::TIME_FORMAT => $this->{Schema::TIME_FORMAT},
            Schema::CREATED_AT => $this->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->{Schema::UPDATED_AT},
        ];
    }
}
