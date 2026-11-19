<?php

namespace Modules\Catalog\Console\Commands;

/**
 * First-time setup: identical protocol to catalog:reindex — on a fresh
 * gate it allocates generation 2, builds the full index, and flips the
 * pointer (generation 1 is the pre-search placeholder state).
 */
final class InstallCatalogSearchCommand extends ReindexCatalogSearchCommand
{
    protected $signature = 'catalog:search:install';

    protected $description = 'Create the first search generation and flip the serving pointer (same barrier protocol as catalog:reindex)';
}
