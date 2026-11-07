<?php

namespace Modules\Order\Schemas\Document;

enum DocumentSequenceTypeEnum: string
{
    case INVOICE = 'invoice';

    case CREDIT_NOTE = 'credit_note';
}
