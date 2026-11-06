<?php

namespace Modules\Core\Contracts\Gateways\Transfer;

/**
 * What an importer does with a row that matches an existing record by
 * its natural key.
 */
enum TransferStrategyEnum: string
{
    /** Overwrite the existing record with the imported payload. */
    case UPDATE = 'update';

    /** Leave the existing record untouched. */
    case SKIP = 'skip';
}
