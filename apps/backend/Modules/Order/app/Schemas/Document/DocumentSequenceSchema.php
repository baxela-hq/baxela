<?php

namespace Modules\Order\Schemas\Document;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Order\Schemas\Module;

class DocumentSequenceSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'document_sequences';

    public const string TYPE = 'type';

    public const string YEAR = 'year';

    public const string LAST_NUMBER = 'last_number';
}
