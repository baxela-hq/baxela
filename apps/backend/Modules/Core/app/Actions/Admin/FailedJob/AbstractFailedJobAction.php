<?php

namespace Modules\Core\Actions\Admin\FailedJob;

use Modules\Core\Exceptions\FailedJob\FailedJobNotFoundException;
use Modules\Core\Models\FailedJob;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;

abstract class AbstractFailedJobAction
{
    public function __construct(protected FailedJob $model) {}

    /**
     * The failed row addressed by uuid — actions pre-check existence before
     * touching queue machinery so a not-found uuid is a clean 404, never an
     * Artisan exit-code guess (gap-B3 D2, verified framework behavior).
     */
    protected function findFailedJob(string $uuid): FailedJob
    {
        $failedJob = $this->model->query()->where(FailedJobSchema::UUID, $uuid)->first();

        if ($failedJob === null) {
            throw new FailedJobNotFoundException;
        }

        return $failedJob;
    }
}
