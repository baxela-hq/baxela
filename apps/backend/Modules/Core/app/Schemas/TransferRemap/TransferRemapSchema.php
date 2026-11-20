<?php

namespace Modules\Core\Schemas\TransferRemap;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class TransferRemapSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = 'transfer_remaps';

    /**
     * The SOURCE installation whose exported file produced this mapping —
     * never the local identity. Inbound history survives outbound identity
     * rotation and database cloning on purpose (C1): the cloned database
     * also holds the mapped local rows, so the mappings stay valid.
     */
    public const string SOURCE_INSTALLATION = 'source_installation';

    public const string MODULE = 'module';

    /**
     * The section/entity key within the module whose id space the source
     * id belongs to (e.g. `users`, `variants`, `orders`) — modules can
     * carry several id spaces in one file.
     */
    public const string ENTITY = 'entity';

    public const string SOURCE_ID = 'source_id';

    public const string LOCAL_ID = 'local_id';
}
