<?php

namespace Modules\Transfer\Schemas\TransferImport;

enum TransferImportStatusEnum: string
{
    case COMPLETED = 'completed';

    case FAILED = 'failed';
}
