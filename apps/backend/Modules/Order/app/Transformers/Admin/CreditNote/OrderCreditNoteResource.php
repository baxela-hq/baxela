<?php

namespace Modules\Order\Transformers\Admin\CreditNote;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Support\Money;
use Modules\Order\Models\OrderCreditNote;
use Modules\Order\Schemas\OrderCreditNote\OrderCreditNoteSchema;

/**
 * @mixin OrderCreditNote
 */
class OrderCreditNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            OrderCreditNoteSchema::ID => $this->resource->{OrderCreditNoteSchema::ID},
            OrderCreditNoteSchema::INVOICE_ID => $this->resource->{OrderCreditNoteSchema::INVOICE_ID},
            OrderCreditNoteSchema::REFUND_UUID => $this->resource->{OrderCreditNoteSchema::REFUND_UUID},
            OrderCreditNoteSchema::NUMBER => $this->resource->{OrderCreditNoteSchema::NUMBER},
            OrderCreditNoteSchema::STATUS => $this->resource->{OrderCreditNoteSchema::STATUS},
            OrderCreditNoteSchema::ISSUED_AT => $this->resource->{OrderCreditNoteSchema::ISSUED_AT},
            OrderCreditNoteSchema::VOIDED_AT => $this->resource->{OrderCreditNoteSchema::VOIDED_AT},
            OrderCreditNoteSchema::VOID_REASON => $this->resource->{OrderCreditNoteSchema::VOID_REASON},
            OrderCreditNoteSchema::CURRENCY_CODE => $this->resource->{OrderCreditNoteSchema::CURRENCY_CODE},
            OrderCreditNoteSchema::AMOUNT => Money::toDecimal(
                Money::fromDecimal((string) $this->resource->{OrderCreditNoteSchema::AMOUNT})
            ),
            OrderCreditNoteSchema::REASON => $this->resource->{OrderCreditNoteSchema::REASON},
            OrderCreditNoteSchema::PDF_STATUS => $this->resource->{OrderCreditNoteSchema::PDF_STATUS},
        ];
    }
}
