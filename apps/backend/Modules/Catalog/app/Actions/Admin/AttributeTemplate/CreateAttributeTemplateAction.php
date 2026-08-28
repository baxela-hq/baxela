<?php

namespace Modules\Catalog\Actions\Admin\AttributeTemplate;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\AttributeTemplate\CreationFailedException;
use Modules\Catalog\Models\AttributeTemplate;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;
use Throwable;

class CreateAttributeTemplateAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): AttributeTemplate
    {
        try {
            DB::beginTransaction();

            $record = AttributeTemplate::query()->create([
                AttributeTemplateSchema::TITLE => $data[AttributeTemplateSchema::TITLE],
                AttributeTemplateSchema::DESCRIPTION => $data[AttributeTemplateSchema::DESCRIPTION],
                AttributeTemplateSchema::IS_ACTIVE => $data[AttributeTemplateSchema::IS_ACTIVE],
                AttributeTemplateSchema::POSITION => $data[AttributeTemplateSchema::POSITION],
            ]);

            $record->groups()->attach($this->groupPivotData($data));

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(AttributeTemplateSchema::RES_GROUPS);
    }

    /**
     * Map the request's group id order to pivot positions.
     */
    private function groupPivotData(array $data): array
    {
        $pivot = [];
        $groupIds = $data[AttributeTemplateGroupSchema::REQ_GROUP_IDS] ?? [];
        foreach (array_values($groupIds) as $index => $groupId) {
            $pivot[$groupId] = [AttributeTemplateGroupSchema::POSITION => $index];
        }

        return $pivot;
    }
}
