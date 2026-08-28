<?php

namespace Modules\Media\DTOs\Admin;

use Illuminate\Http\UploadedFile;
use Modules\Core\DTOs\Trait\FillTrait;

/**
 * make sync with Modules\Core\Gateways\Media\DTOs\CreateMediaInput
 */
class CreateMediaInput
{
    use FillTrait;

    public UploadedFile $file;

    public ?string $folder_id = null;
}
