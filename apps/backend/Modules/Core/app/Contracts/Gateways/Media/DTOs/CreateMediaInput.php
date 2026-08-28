<?php

namespace Modules\Core\Contracts\Gateways\Media\DTOs;

use Illuminate\Http\UploadedFile;
use Modules\Core\DTOs\Trait\ToArrayTrait;

/**
 * make sync with Modules\Media\DTOs\Admin\CreateMediaInput
 */
class CreateMediaInput
{
    use ToArrayTrait;

    public UploadedFile $file;

    public ModuleNameEnum $module_name;

    public ?string $entity_type = null;

    public ?string $entity_id = null;
}
