<?php

namespace Modules\Order\Gateways\DTOs;

use Modules\Order\Schemas\Order\OrderPaymentStatusEnum;

class GetOrderOutput
{
    public function __construct(array $fields)
    {
        $this->id = $fields['id'];
        $this->order_code = $fields['order_code'];
        $this->user_id = $fields['user_id'] ?? null;
        $this->status = $fields['status'];
        $this->payment_status = $fields['payment_status'];
        $this->total_amount = $fields['total_amount'];
        $this->is_payable = $this->payment_status === OrderPaymentStatusEnum::UNPAID->value;
    }

    public int $id;

    public string $order_code;

    public ?int $user_id = null;

    public float $total_amount;

    public string $status;

    public string $payment_status;

    /** Derived: payment is still open (UNPAID), so the order may be paid. */
    public bool $is_payable;
}
