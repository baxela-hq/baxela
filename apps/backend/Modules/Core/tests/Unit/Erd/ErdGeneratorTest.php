<?php

use Modules\Core\Support\Erd\ColumnInfo;
use Modules\Core\Support\Erd\ErdEdge;
use Modules\Core\Support\Erd\ErdGenerator;
use Modules\Core\Support\Erd\IndexInfo;
use Modules\Core\Support\Erd\ModelRelationsScanner;
use Modules\Core\Support\Erd\ModuleTableResolver;
use Modules\Core\Support\Erd\MorphNote;
use Modules\Core\Support\Erd\ScanResult;
use Modules\Core\Support\Erd\SchemaIntrospector;
use Modules\Core\Support\Erd\SchemaSnapshot;
use Modules\Core\Support\Erd\TableInfo;

/**
 * @param  array<string, TableInfo>  $tables
 * @param  array<int, ErdEdge>  $fkEdges
 * @param  array<int, ErdEdge>  $appEdges
 * @param  array<int, MorphNote>  $morphNotes
 * @param  array<string, string>  $tableModules
 */
function erdTestGenerator(array $tables, array $fkEdges, array $appEdges, array $morphNotes = [], array $tableModules = []): ErdGenerator
{
    $map = [];
    foreach ($tables as $table) {
        $map[$table->table] = $tableModules[$table->table] ?? 'Shop';
    }

    $introspector = new class(new SchemaSnapshot($tables, $fkEdges)) extends SchemaIntrospector
    {
        public function __construct(protected readonly SchemaSnapshot $testSnapshot) {}

        public function snapshot(): SchemaSnapshot
        {
            return $this->testSnapshot;
        }
    };

    $scanner = new class(new ScanResult([], $appEdges, $morphNotes, $tableModules, [])) extends ModelRelationsScanner
    {
        public function __construct(protected readonly ScanResult $testResult) {}

        public function scan(): ScanResult
        {
            return $this->testResult;
        }
    };

    $resolver = new class($map) extends ModuleTableResolver
    {
        /** @param array<string, string> $testMap */
        public function __construct(protected readonly array $testMap) {}

        public function resolve(array $tableModules, array $allTables): array
        {
            return $this->testMap;
        }
    };

    return new ErdGenerator($introspector, $scanner, $resolver);
}

/**
 * @param  array<int, ColumnInfo>  $columns
 * @param  array<int, IndexInfo>  $indexes
 */
function erdTableFixture(string $name, array $columns, array $indexes = []): TableInfo
{
    return new TableInfo($name, '', $columns, $indexes);
}

it('drops app edges already covered by a database foreign key', function () {
    $tables = [
        'posts' => erdTableFixture('posts', [new ColumnInfo('id', 'bigint'), new ColumnInfo('user_id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'users' => erdTableFixture('users', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    $fk = [new ErdEdge('posts', 'users', ['user_id'], ['id'], ErdEdge::KIND_FK)];
    $app = [new ErdEdge('posts', 'users', ['user_id'], ['id'], ErdEdge::KIND_APP)];

    $document = erdTestGenerator($tables, $fk, $app)->generate();

    expect($document->edges)->toHaveCount(1)
        ->and($document->edges[0]->kind)->toBe(ErdEdge::KIND_FK);
});

it('collapses duplicate app edges from both sides of one relation', function () {
    $tables = [
        'comments' => erdTableFixture('comments', [new ColumnInfo('id', 'bigint'), new ColumnInfo('post_id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'posts' => erdTableFixture('posts', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    // hasMany(PostComment) + belongsTo(Product) on the same relation
    $app = [
        new ErdEdge('comments', 'posts', ['post_id'], ['id'], ErdEdge::KIND_APP),
        new ErdEdge('comments', 'posts', ['post_id'], ['id'], ErdEdge::KIND_APP),
    ];

    $document = erdTestGenerator($tables, [], $app)->generate();

    expect($document->edges)->toHaveCount(1)
        ->and($document->edges[0]->kind)->toBe(ErdEdge::KIND_APP);
});

it('enriches app edges with nullability and single-column uniqueness', function () {
    $tables = [
        'profiles' => erdTableFixture('profiles', [
            new ColumnInfo('id', 'bigint'),
            new ColumnInfo('user_id', 'bigint', nullable: true),
            new ColumnInfo('bio', 'text'),
        ], [
            new IndexInfo('primary', ['id'], primary: true),
            new IndexInfo('profiles_user_id_unique', ['user_id'], unique: true),
        ]),
        'users' => erdTableFixture('users', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    $app = [new ErdEdge('profiles', 'users', ['user_id'], ['id'], ErdEdge::KIND_APP)];

    $edge = erdTestGenerator($tables, [], $app)->generate()->edges[0];

    expect($edge->nullable)->toBeTrue()
        ->and($edge->unique)->toBeTrue();
});

it('lists only truly unresolved reference columns with inferred targets', function () {
    $tables = [
        'products' => erdTableFixture('products', [
            new ColumnInfo('id', 'bigint'),
            new ColumnInfo('brand_id', 'bigint'),
            new ColumnInfo('language_id', 'bigint'),
            new ColumnInfo('featuredable_id', 'bigint'),
            new ColumnInfo('owner_id', 'bigint'),
        ], [new IndexInfo('primary', ['id'], primary: true)]),
        'brands' => erdTableFixture('brands', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'languages' => erdTableFixture('languages', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    $fk = [new ErdEdge('products', 'brands', ['brand_id'], ['id'], ErdEdge::KIND_FK)];
    $app = [new ErdEdge('products', 'languages', ['language_id'], ['id'], ErdEdge::KIND_APP)];
    $morph = [new MorphNote('products', 'featuredable_type', 'featuredable_id')];

    $unresolved = erdTestGenerator($tables, $fk, $app, $morph)->generate()->unresolvedRefs;

    expect($unresolved)->toBe(['products.owner_id' => null]);
});

it('marks ambiguous inferred targets', function () {
    $tables = [
        'posts' => erdTableFixture('posts', [new ColumnInfo('id', 'bigint'), new ColumnInfo('user_id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'auth_users' => erdTableFixture('auth_users', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'crm_users' => erdTableFixture('crm_users', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    $unresolved = erdTestGenerator($tables, [], [])->generate()->unresolvedRefs;

    expect($unresolved)->toBe(['posts.user_id' => 'auth_users / crm_users (ambiguous)']);
});

it('collects cross-module dependency pairs from both edge kinds', function () {
    $tables = [
        'shop_orders' => erdTableFixture('shop_orders', [new ColumnInfo('id', 'bigint'), new ColumnInfo('variant_id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'catalog_variants' => erdTableFixture('catalog_variants', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'catalog_brands' => erdTableFixture('catalog_brands', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    $map = [
        'shop_orders' => 'Order',
        'catalog_variants' => 'Catalog',
        'catalog_brands' => 'Catalog',
    ];

    $app = [
        new ErdEdge('shop_orders', 'catalog_variants', ['variant_id'], ['id'], ErdEdge::KIND_APP),
        new ErdEdge('catalog_variants', 'catalog_brands', ['brand_id'], ['id'], ErdEdge::KIND_APP),
    ];

    $dependencies = erdTestGenerator($tables, [], $app, [], $map)->generate()->dependencies;

    expect($dependencies)->toBe([['Order', 'Catalog']]);
});

it('attaches resolved modules to every table', function () {
    $tables = [
        'shop_orders' => erdTableFixture('shop_orders', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
        'users' => erdTableFixture('users', [new ColumnInfo('id', 'bigint')], [new IndexInfo('primary', ['id'], primary: true)]),
    ];

    $map = ['shop_orders' => 'Order', 'users' => ModuleTableResolver::OTHER];
    $document = erdTestGenerator($tables, [], [], [], $map)->generate();

    expect($document->moduleOf('shop_orders'))->toBe('Order')
        ->and($document->moduleOf('users'))->toBe(ModuleTableResolver::OTHER)
        ->and($document->modules())->toBe(['Order', 'other']);
});
