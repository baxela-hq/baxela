<?php

namespace Modules\Core\Contracts\Gateways\Payment\DTOs;

class WebhookResult
{
    public function __construct(
        public readonly string $transaction_id,
        public readonly string $status,
    ) {}
}
