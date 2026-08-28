<?php

namespace Modules\Catalog\Actions\Admin\AttributeTemplate;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\AttributeTemplate\UpdateFailedException;
use Modules\Catalog\Models\AttributeTemplate;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateGroupSchema;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;
use Throwable;

class UpdateAttributeTemplateAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): AttributeTemplate
    {
        $record = AttributeTemplate::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                AttributeTemplateSchema::TITLE => $data[AttributeTemplateSchema::TITLE],
                AttributeTemplateSchema::DESCRIPTION => $data[AttributeTemplateSchema::DESCRIPTION],
                AttributeTemplateSchema::IS_ACTIVE => $data[AttributeTemplateSchema::IS_ACTIVE],
                AttributeTemplateSchema::POSITION => $data[AttributeTemplateSchema::POSITION],
            ]);

            $record->groups()->sync($this->groupPivotData($data));

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
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
