<?php

namespace Modules\Core\Transformers\Admin\FailedJob;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;
use Modules\Core\Support\FailedJob\FailedJobPayloadPresenter;

/**
 * Failed-job admin surface (gap-B3 D2/D3). List rows carry the
 * server-side-truncated exception excerpt; the show endpoint adds the full
 * exception and the redacted payload — the serialized command never leaves
 * the backend.
 *
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property \Carbon\CarbonInterface|null $failed_at
 */
class FailedJobResource extends JsonResource
{
    public function __construct($resource, protected bool $detailed = false)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     */
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $presenter = app(FailedJobPayloadPresenter::class);

        $data = [
            FailedJobSchema::UUID => $this->{FailedJobSchema::UUID},
            FailedJobSchema::RES_JOB => $presenter->displayName($this->{FailedJobSchema::PAYLOAD}),
            FailedJobSchema::CONNECTION => $this->{FailedJobSchema::CONNECTION},
            FailedJobSchema::QUEUE => $this->{FailedJobSchema::QUEUE},
            FailedJobSchema::FAILED_AT => $this->{FailedJobSchema::FAILED_AT}?->toISOString(),
            FailedJobSchema::EXCEPTION => $presenter->exceptionExcerpt($this->{FailedJobSchema::EXCEPTION}),
        ];

        if (! $this->detailed) {
            return $data;
        }

        $data[FailedJobSchema::EXCEPTION] = trim($this->{FailedJobSchema::EXCEPTION});
        $data[FailedJobSchema::PAYLOAD] = $presenter->present($this->{FailedJobSchema::PAYLOAD});

        return $data;
    }
}
