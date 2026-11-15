<?php

namespace Modules\Core\Transformers\Transfer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\TransferImport;

/** Structure check result of an uploaded transfer file. */
/** @mixin TransferImport */
class TransferImportPreviewResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $this->resource
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'filename' => $this->resource['filename'],
            'format' => $this->resource['format'],
            'module' => $this->resource['module'],
            'version' => $this->resource['version'],
            'exported_at' => $this->resource['exported_at'],
            'sections' => $this->resource['sections'],
            'total_rows' => $this->resource['total_rows'],
            'row_cap' => $this->resource['row_cap'],
            'warnings' => $this->resource['warnings'],
        ];
    }
}
