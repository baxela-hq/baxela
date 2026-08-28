<?php

namespace Modules\Core\Contracts\Gateways\Media;

use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaInput;
use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaOutput;

interface MediaGatewayInterface
{
    public function create(CreateMediaInput $input): ?CreateMediaOutput;

    public function delete(string $id): bool;
}
