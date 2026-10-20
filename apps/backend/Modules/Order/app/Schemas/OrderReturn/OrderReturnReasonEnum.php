<?php

namespace Modules\Order\Schemas\OrderReturn;

enum OrderReturnReasonEnum: string
{
    case DAMAGED = 'damaged';

    case WRONG_ITEM = 'wrong_item';

    case NOT_AS_DESCRIBED = 'not_as_described';

    case OTHER = 'other';
}
