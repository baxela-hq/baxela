<?php

namespace Modules\Media\Gateways;

use Exception;
use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaInput;
use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaOutput;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Media\Actions\Admin\Media\CreateMediaAction;
use Modules\Media\Actions\Admin\Media\DeleteMediaAction;
use Modules\Media\DTOs\Admin\CreateMediaInput as CreateMediaInputDto;

class MediaGateway implements MediaGatewayInterface
{
    /**
     * @throws Exception
     */
    public function create(CreateMediaInput $input): ?CreateMediaOutput
    {
        $action = app(CreateMediaAction::class);
        $dto = CreateMediaInputDto::fill($input->toArray());

        $result = $action->handle($dto);

        if (! $result) {
            return null;
        }

        return CreateMediaOutput::fill($result->toArray());
    }

    /**
     * @throws Exception
     */
    public function delete(string $id): bool
    {
        $action = app(DeleteMediaAction::class);

        return $action->handle($id);
    }
}
