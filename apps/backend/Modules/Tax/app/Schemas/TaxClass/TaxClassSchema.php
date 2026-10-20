<?php

namespace Modules\Tax\Schemas\TaxClass;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Tax\Schemas\Module;

class TaxClassSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'classes';

    public const string NAME = 'name';

    /**
     * Exactly one class carries this flag (transactional promote/demote);
     * products with a null tax_class_id resolve to it at calculation time.
     */
    public const string IS_DEFAULT = 'is_default';

    /**
     * Exactly one class carries this flag; shipping cost is taxed only
     * through this class's country rates. Names are labels — flags carry
     * behavior.
     */
    public const string IS_SHIPPING = 'is_shipping';

    public const string RES_RATES = 'rates';
}
