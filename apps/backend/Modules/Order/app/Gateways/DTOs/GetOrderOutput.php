<?php

namespace Modules\Order\Gateways\DTOs;

class GetOrderOutput
{
    public function __construct(array $fields)
    {
        $this->id = $fields['id'];
        $this->status = $fields['status'];
        $this->total_amount = $fields['total_amount'];
    }

    public int $id;

    public float $total_amount;

    public string $status;
}
