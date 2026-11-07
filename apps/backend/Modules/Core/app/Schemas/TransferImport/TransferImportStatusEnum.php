<?php

namespace Modules\Core\Schemas\TransferImport;

enum TransferImportStatusEnum: string
{
    case COMPLETED = 'completed';

    case FAILED = 'failed';
}
