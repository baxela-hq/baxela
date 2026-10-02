<?php

namespace Modules\Catalog\Support\DataTransfer;

use Modules\Catalog\Schemas\Module;

/**
 * Contract of the catalog module data export file. Rows carry the exact
 * JSON payloads the admin create/update endpoints accept, grouped into
 * sections ordered by dependency — every section may only reference rows
 * of sections before it, so a sequential replay can resolve references
 * as it goes.
 */
final class CatalogTransferFormat
{
    public const string FORMAT = 'baxela.module-export';

    public const string MODULE = Module::NAME_LOWER;

    public const int VERSION = 1;

    /** Total row cap across all sections of one import file. */
    public const int ROW_CAP = 5000;

    public const string KEY_FORMAT = 'format';

    public const string KEY_MODULE = 'module';

    public const string KEY_VERSION = 'version';

    public const string KEY_EXPORTED_AT = 'exported_at';

    public const string KEY_SECTIONS = 'sections';

    public const string KEY_ENTITY = 'entity';

    public const string KEY_ROWS = 'rows';

    public const string KEY_SOURCE_ID = 'source_id';

    public const string KEY_OWNER = 'owner';

    public const string KEY_PAYLOAD = 'payload';

    public const string SECTION_ATTRIBUTE_GROUPS = 'attribute-groups';

    public const string SECTION_ATTRIBUTES = 'attributes';

    public const string SECTION_ATTRIBUTE_VALUES = 'attribute-values';

    public const string SECTION_OPTIONS = 'options';

    public const string SECTION_OPTION_VALUES = 'option-values';

    public const string SECTION_CATEGORIES = 'categories';

    public const string SECTION_PRODUCTS = 'products';

    /**
     * Import order: reference data before the entities referencing it.
     * Parent-child sections keep their producer order (attributes before
     * attribute-values, options before option-values).
     *
     * @return array<int, string>
     */
    public static function sectionOrder(): array
    {
        return [
            self::SECTION_ATTRIBUTE_GROUPS,
            self::SECTION_ATTRIBUTES,
            self::SECTION_ATTRIBUTE_VALUES,
            self::SECTION_OPTIONS,
            self::SECTION_OPTION_VALUES,
            self::SECTION_CATEGORIES,
            self::SECTION_PRODUCTS,
        ];
    }
}
