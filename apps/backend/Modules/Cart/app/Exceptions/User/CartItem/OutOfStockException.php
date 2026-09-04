<?php

namespace Modules\Cart\Exceptions\User\CartItem;

use Modules\Cart\Exceptions\ErrorCodeEnum;
use Modules\Core\Exceptions\BaseException;
use Throwable;

class OutOfStockException extends BaseException
{
    public function __construct(
        string $itemName,
        int $available,
        int $variantId,
        int $httpStatus = 400,
        bool $isSafe = true,
        ?Throwable $previous = null,
    ) {
        $code = $available > 0
            ? ErrorCodeEnum::ITEM_INSUFFICIENT_STOCK->value
            : ErrorCodeEnum::ITEM_OUT_OF_STOCK->value;

        parent::__construct(
            $code,
            $httpStatus,
            [
                'variant_id' => $variantId,
                'available' => $available,
            ],
            $isSafe,
            $previous,
            ['name' => $itemName, 'available' => $available],
        );
    }
}
