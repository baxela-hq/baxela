<?php

namespace Modules\Payment\Transformers\Admin\DriverCredential;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Credential STATES only — plaintext, ciphertext and fragments never
 * leave the server (marketplace P5.2 D5/D8).
 *
 * @property array<string, mixed> $resource
 */
class DriverCredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'driver' => $this->resource['driver'],
            'keys' => $this->resource['keys'],
            'is_configured' => $this->resource['is_configured'],
        ];
    }
}
