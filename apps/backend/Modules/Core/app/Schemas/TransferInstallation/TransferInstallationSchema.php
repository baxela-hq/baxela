<?php

namespace Modules\Core\Schemas\TransferInstallation;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class TransferInstallationSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = 'transfer_installation';

    public const string UUID = 'uuid';

    /**
     * The single row's fixed primary key: this table holds exactly one
     * record — this installation's outbound identity (C1).
     */
    public const int FIXED_ID = 1;
}
