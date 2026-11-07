<?php

namespace Modules\Media\Transformers\Admin\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Schemas\Media\MediaSchema;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk($this->resource->{MediaSchema::DISK});

        return [
            MediaSchema::ID => $this->resource->{MediaSchema::ID},
            MediaSchema::USER_ID => $this->resource->{MediaSchema::USER_ID},
            MediaSchema::FOLDER_ID => $this->resource->{MediaSchema::FOLDER_ID},
            MediaSchema::DISK => $this->resource->{MediaSchema::DISK},
            MediaSchema::RES_URL => $disk->url($this->resource->{MediaSchema::PATH}),
            MediaSchema::PATH => $this->resource->{MediaSchema::PATH},
            MediaSchema::NAME => $this->resource->{MediaSchema::NAME},
            MediaSchema::FILENAME => $this->resource->{MediaSchema::FILENAME},
            MediaSchema::EXTENSION => $this->resource->{MediaSchema::EXTENSION},
            MediaSchema::MIME_TYPE => $this->resource->{MediaSchema::MIME_TYPE},
            MediaSchema::SIZE => $this->resource->{MediaSchema::SIZE},
            MediaSchema::METADATA => $this->resource->{MediaSchema::METADATA},
            // Variant URLs resolve against the record's disk, exactly like
            // the original's; only completed entries are exposed (gap-C3).
            MediaSchema::RES_VARIANTS => $this->resolvedVariants($disk),
            MediaSchema::CREATED_AT => $this->resource->{MediaSchema::CREATED_AT},
            MediaSchema::UPDATED_AT => $this->resource->{MediaSchema::UPDATED_AT},
        ];
    }

    /**
     * @return array<string, array{url: string, width: int, height: int}>
     */
    private function resolvedVariants($disk): array
    {
        $resolved = [];

        foreach ((array) ($this->resource->{MediaSchema::METADATA}['variants'] ?? []) as $name => $variant) {
            if (($variant['status'] ?? null) !== 'completed' || empty($variant['path'])) {
                continue;
            }

            $resolved[$name] = [
                'url' => $disk->url((string) $variant['path']),
                'width' => (int) ($variant['width'] ?? 0),
                'height' => (int) ($variant['height'] ?? 0),
            ];
        }

        return $resolved;
    }
}
