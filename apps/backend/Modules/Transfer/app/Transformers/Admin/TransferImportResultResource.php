<?php

namespace Modules\Transfer\Transformers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Summary of one transfer import run (dry runs included). */
class TransferImportResultResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $this->resource
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'module' => $this->resource['module'],
            'status' => $this->resource['status'],
            'strategy' => $this->resource['strategy'],
            'dry_run' => $this->resource['dry_run'],
            'total_rows' => $this->resource['total_rows'],
            'created_count' => $this->resource['created_count'],
            'updated_count' => $this->resource['updated_count'],
            'skipped_count' => $this->resource['skipped_count'],
            'failed_count' => $this->resource['failed_count'],
            'duration_ms' => $this->resource['duration_ms'],
            'sections' => $this->resource['sections'],
            'warnings' => $this->resource['warnings'],
            'errors' => $this->resource['errors'],
        ];
    }
}
