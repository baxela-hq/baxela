<?php

namespace Modules\Core\Exceptions\Extension;

use Modules\Core\Exceptions\BaseException;
use Throwable;

class ExtensionsException extends BaseException
{
    /**
     * @param  string|null  $code
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $messageParams
     */
    public function __construct(
        $code = null,
        int $httpStatus = 400,
        array $meta = [],
        bool $isSafe = true,
        ?Throwable $previous = null,
        array $messageParams = [],
    ) {
        parent::__construct($code ?? ErrorCodeEnum::EXTENSIONS_NOT_INSTALLED->value, $httpStatus, $meta, $isSafe, $previous, $messageParams);
    }

    /**
     * Enabling requires the extension to be installed: a registry row
     * must exist AND the Composer package must be present in vendor.
     * `:slug` names the extension in the translated message.
     */
    public static function notInstalled(string $slug): self
    {
        return new self(
            ErrorCodeEnum::EXTENSIONS_NOT_INSTALLED->value,
            422,
            messageParams: ['slug' => $slug],
        );
    }
}
