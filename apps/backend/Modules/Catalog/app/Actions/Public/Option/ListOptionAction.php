<?php

namespace Modules\Catalog\Actions\Public\Option;

use Illuminate\Http\Request;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;

class ListOptionAction extends AbstractOptionAction
{
    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->model
            ->with([
                OptionSchema::RES_VALUES => function ($query): void {
                    $query->orderBy(OptionValueSchema::POSITION);
                },
            ])
            ->with(OptionSchema::RES_TRANSLATIONS)
            ->with(OptionSchema::RES_VALUES.'.'.OptionValueSchema::RES_TRANSLATIONS)
            ->orderBy(OptionSchema::POSITION)
            ->paginate($perPage)
            ->withQueryString();
    }
}
