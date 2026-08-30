<?php

namespace Modules\Shipping\Actions\Admin\Method;

use Illuminate\Support\Facades\DB;
use Modules\Shipping\Exceptions\Method\UpdateFailedException;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Throwable;

class UpdateMethodAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Method
    {
        $record = Method::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                MethodSchema::CODE => $data[MethodSchema::CODE],
                MethodSchema::IS_ACTIVE => $data[MethodSchema::IS_ACTIVE],
                MethodSchema::POSITION => $data[MethodSchema::POSITION],
            ]);

            $record->translations()->delete();
            foreach ($data[MethodSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record->load(MethodSchema::RES_TRANSLATIONS);
    }
}
