<?php

namespace Modules\Order\Schemas\OrderCreditNote;

enum OrderCreditNoteStatusEnum: string
{
    case ACTIVE = 'active';

    case VOID = 'void';
}
