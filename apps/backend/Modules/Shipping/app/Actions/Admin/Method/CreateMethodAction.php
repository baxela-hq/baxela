<?php

namespace Modules\Shipping\Actions\Admin\Method;

use Illuminate\Support\Facades\DB;
use Modules\Shipping\Exceptions\Method\CreationFailedException;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Throwable;

class CreateMethodAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Method
    {
        try {
            DB::beginTransaction();

            $record = Method::query()->create([
                MethodSchema::CODE => $data[MethodSchema::CODE],
                MethodSchema::IS_ACTIVE => $data[MethodSchema::IS_ACTIVE],
                MethodSchema::POSITION => $data[MethodSchema::POSITION],
            ]);

            foreach ($data[MethodSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(MethodSchema::RES_TRANSLATIONS);
    }
}
