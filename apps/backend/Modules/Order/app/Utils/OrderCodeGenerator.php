<?php

namespace Modules\Order\Utils;

use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;

class OrderCodeGenerator
{
    /**
     * Unambiguous 31-char alphabet — no 0/O, 1/I/L.
     *
     * 31^8 ≈ 852 billion combinations; the unique index backstops collisions.
     */
    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    /**
     * A collision retry would be astronomically rare; bound it anyway.
     */
    private const int MAX_RETRIES = 5;

    public function generate(): string
    {
        $alphabetLength = strlen(self::ALPHABET);

        $attempts = 0;
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
            }
            $attempts++;
            $exists = Order::query()->where(OrderSchema::ORDER_CODE, $code)->exists();
        } while ($exists && $attempts < self::MAX_RETRIES);

        return $code;
    }
}
