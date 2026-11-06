<?php

use Modules\Core\Support\Erd\ColumnInfo;
use Modules\Core\Support\Erd\ErdDocument;
use Modules\Core\Support\Erd\ErdEdge;
use Modules\Core\Support\Erd\IndexInfo;
use Modules\Core\Support\Erd\MermaidErdFormatter;
use Modules\Core\Support\Erd\MorphNote;
use Modules\Core\Support\Erd\TableInfo;

function erdTestDocument(): ErdDocument
{
    $products = new TableInfo('shop_products', 'Shop', [
        new ColumnInfo('id', 'bigint unsigned'),
        new ColumnInfo('brand_id', 'bigint unsigned', nullable: true),
        new ColumnInfo('sku', 'varchar(255)'),
        new ColumnInfo('language_id', 'bigint unsigned'),
        new ColumnInfo('tax_class_id', 'bigint unsigned'),
    ], [
        new IndexInfo('primary', ['id'], primary: true),
        new IndexInfo('shop_products_sku_unique', ['sku'], unique: true),
    ]);

    $brands = new TableInfo('shop_brands', 'Shop', [
        new ColumnInfo('id', 'bigint unsigned'),
    ], [new IndexInfo('primary', ['id'], primary: true)]);

    $details = new TableInfo('shop_details', 'Shop', [
        new ColumnInfo('id', 'bigint unsigned'),
        new ColumnInfo('product_id', 'bigint unsigned'),
    ], [new IndexInfo('primary', ['id'], primary: true)]);

    $profiles = new TableInfo('shop_profiles', 'Shop', [
        new ColumnInfo('id', 'bigint unsigned'),
        new ColumnInfo('user_id', 'bigint unsigned'),
    ], [new IndexInfo('primary', ['id'], primary: true), new IndexInfo('shop_profiles_user_id_unique', ['user_id'], unique: true)]);

    $featured = new TableInfo('shop_featured', 'Shop', [
        new ColumnInfo('id', 'bigint unsigned'),
        new ColumnInfo('featuredable_type', 'varchar(255)'),
        new ColumnInfo('featuredable_id', 'bigint unsigned'),
    ], [new IndexInfo('primary', ['id'], primary: true)]);

    $languages = new TableInfo('core_languages', 'Core', [
        new ColumnInfo('id', 'bigint unsigned'),
    ], [new IndexInfo('primary', ['id'], primary: true)]);

    $countries = new TableInfo('core_countries', 'Core', [
        new ColumnInfo('id', 'bigint unsigned'),
        new ColumnInfo('code', 'char(2)'),
    ], [new IndexInfo('primary', ['id'], primary: true)]);

    $users = new TableInfo('auth_users', 'Auth', [
        new ColumnInfo('id', 'bigint unsigned'),
    ], [new IndexInfo('primary', ['id'], primary: true)]);

    $edges = [
        new ErdEdge('shop_products', 'shop_brands', ['brand_id'], ['id'], ErdEdge::KIND_FK, nullable: true),
        new ErdEdge('shop_details', 'shop_products', ['product_id'], ['id'], ErdEdge::KIND_FK),
        new ErdEdge('shop_products', 'core_languages', ['language_id'], ['id'], ErdEdge::KIND_APP),
        new ErdEdge('shop_profiles', 'auth_users', ['user_id'], ['id'], ErdEdge::KIND_APP, unique: true),
        new ErdEdge('shop_zones', 'core_countries', ['country_code'], ['code'], ErdEdge::KIND_APP),
    ];

    return new ErdDocument(
        ['shop_products' => $products, 'shop_brands' => $brands, 'shop_details' => $details, 'shop_profiles' => $profiles, 'shop_featured' => $featured, 'core_languages' => $languages, 'core_countries' => $countries, 'auth_users' => $users],
        $edges,
        [new MorphNote('shop_featured', 'featuredable_type', 'featuredable_id', ['product'])],
        [
            'shop_products.tax_class_id' => null,
            'shop_products.user_id' => 'auth_users / user_users (ambiguous)',
        ],
        [['Shop', 'Auth'], ['Shop', 'Core']],
    );
}

it('renders entity blocks with pk, fk and uk badges', function () {
    $page = (new MermaidErdFormatter)->formatModule('Shop', erdTestDocument());

    expect($page)->toContain('    shop_products {')
        ->and($page)->toContain('        bigint_unsigned id PK')
        ->and($page)->toContain('        bigint_unsigned brand_id FK "→ shop_brands"')
        ->and($page)->toContain('        varchar sku UK')
        ->and($page)->toContain('        bigint_unsigned product_id FK "→ shop_products"');
});

it('renders all four cardinalities solid and dashed', function () {
    $page = (new MermaidErdFormatter)->formatModule('Shop', erdTestDocument());

    expect($page)->toContain('shop_products }o--o| shop_brands : "brand_id"')
        ->and($page)->toContain('shop_details }o--|| shop_products : "product_id"')
        ->and($page)->toContain('shop_products }o..|| core_languages : "language_id"')
        ->and($page)->toContain('shop_profiles ||..o| auth_users : "user_id"');
});

it('marks edge endpoints from other modules as external', function () {
    $page = (new MermaidErdFormatter)->formatModule('Shop', erdTestDocument());

    expect($page)->toContain('%% external tables:')
        ->and($page)->toContain('%% core_languages (owned by Core)')
        ->and($page)->toContain('%% auth_users (owned by Auth)');
});

it('renders the foreign key column when the owner key is not id', function () {
    // shop_zones belongs to a third module: the edge shows up on the Core
    // page (owner of core_countries), with shop_zones as an external endpoint.
    $page = (new MermaidErdFormatter)->formatModule('Core', erdTestDocument());

    expect($page)->toContain('shop_zones }o..|| core_countries : "country_code → code"')
        ->and($page)->toContain('%% shop_zones (owned by unknown)');
});

it('renders polymorphic notes and unresolved reference columns', function () {
    $page = (new MermaidErdFormatter)->formatModule('Shop', erdTestDocument());

    expect($page)->toContain('| `shop_featured` | `featuredable_type`, `featuredable_id` | product (morph map) |')
        ->and($page)->toContain('### Unresolved reference columns')
        ->and($page)->toContain('- `shop_products.tax_class_id`')
        ->and($page)->toContain('- `shop_products.user_id` → `auth_users / user_users (ambiguous)`?');
});

it('renders the index with the module map and dependency graph', function () {
    $index = (new MermaidErdFormatter)->formatIndex(erdTestDocument());

    expect($index)->toContain('| [Shop](shop.md) | 5 | 2 | 2 | 2 |')
        ->and($index)->toContain('| [Core](core.md) | 2 | 0 | 0 | 0 |')
        ->and($index)->toContain('    shop --> auth')
        ->and($index)->toContain('    shop --> core');
});

it('produces byte-identical output for repeated formatting', function () {
    $formatter = new MermaidErdFormatter;
    $document = erdTestDocument();

    expect($formatter->formatModule('Shop', $document))
        ->toBe($formatter->formatModule('Shop', $document))
        ->and($formatter->formatIndex($document))->toBe($formatter->formatIndex($document));
});
