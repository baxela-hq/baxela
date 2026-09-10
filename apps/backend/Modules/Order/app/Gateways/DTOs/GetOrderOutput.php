<?php

namespace Modules\Order\Gateways\DTOs;

class GetOrderOutput
{
    public function __construct(array $fields)
    {
        $this->id = $fields['id'];
        $this->order_code = $fields['order_code'];
        $this->user_id = $fields['user_id'] ?? null;
        $this->status = $fields['status'];
        $this->total_amount = $fields['total_amount'];
    }

    public int $id;

    public string $order_code;

    public ?int $user_id = null;

    public float $total_amount;

    public string $status;
}
