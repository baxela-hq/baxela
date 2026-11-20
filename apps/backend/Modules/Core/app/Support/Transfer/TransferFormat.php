<?php

namespace Modules\Core\Support\Transfer;

/**
 * Contract of the platform module data-transfer file (the import/export
 * standard): one JSON envelope per module, rows carrying the exact JSON
 * payloads the owning module's admin create/update endpoints accept,
 * grouped into sections ordered by dependency — every section may only
 * reference rows of sections before it, so a sequential replay can
 * resolve references as it goes.
 */
final class TransferFormat
{
    public const string FORMAT = 'baxela.module-export';

    public const int VERSION = 1;

    public const string KEY_FORMAT = 'format';

    public const string KEY_MODULE = 'module';

    public const string KEY_VERSION = 'version';

    public const string KEY_EXPORTED_AT = 'exported_at';

    /**
     * Additive v1 envelope key (C1): the exporting installation's public
     * identity. Optional by design — legacy readers ignore unknown keys,
     * and legacy files without it import into the reserved
     * `unidentified` remap namespace.
     */
    public const string KEY_SOURCE_INSTALLATION = 'source_installation';

    public const string KEY_SECTIONS = 'sections';

    public const string KEY_ENTITY = 'entity';

    public const string KEY_ROWS = 'rows';
}
