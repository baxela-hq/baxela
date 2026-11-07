<?php

namespace Modules\Transfer\Actions\Admin\Capabilities;

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Support\TransferRegistry;

/**
 * The coverage matrix of the data-transfer standard: every enabled
 * module with the sections it exposes for export/import — empty for
 * modules that have not opted in, so admins see exactly where the
 * platform stands. Supported modules also publish their own data
 * endpoint URLs, resolved from the registered routes: a module without
 * (fully wired) per-module endpoints publishes none.
 */
class ShowTransferCapabilitiesAction
{
    /**
     * The per-module endpoint actions published in the matrix, mapped to
     * their route-name leaf segments.
     */
    private const array ENDPOINT_ACTIONS = [
        'export' => 'export',
        'import_preview' => 'import.preview',
        'import' => 'import',
        'imports' => 'imports.list',
    ];

    public function __construct(protected TransferRegistry $registry) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(): array
    {
        $capabilities = [];
        foreach ($this->registry->enabledModuleNames() as $module) {
            $transfer = $this->registry->forModule($module);

            $sections = [];
            if ($transfer !== null) {
                foreach ($transfer->sections() as $section) {
                    $sections[] = [
                        'key' => $section->key,
                        'exportable' => $section->exportable,
                        'importable' => $section->importable,
                    ];
                }
            }

            $capabilities[] = [
                'module' => $module,
                'supported' => $transfer !== null,
                'endpoints' => $this->endpoints($module),
                'sections' => $sections,
            ];
        }

        return $capabilities;
    }

    /**
     * The module's own data-endpoint URLs, relative to the API root the
     * admin client calls. Resolved from the registered routes — the
     * source of truth for what actually exists — and published all-or-
     * nothing so a half-wired module never advertises a broken surface.
     *
     * @return array{export: string, import_preview: string, import: string, imports: string}|null
     */
    private function endpoints(string $module): ?array
    {
        $endpoints = [];
        foreach (self::ENDPOINT_ACTIONS as $key => $leaf) {
            $route = Route::getRoutes()->getByName("api.{$module}.admin.data.{$leaf}");
            if ($route === null) {
                return null;
            }

            $endpoints[$key] = $this->relativeUri($route->uri());
        }

        return $endpoints;
    }

    /**
     * Routes register under `api/v1/...`; the admin client's base URL
     * already ends in /api/v1, so publish the remainder.
     */
    private function relativeUri(string $uri): string
    {
        return str_starts_with($uri, 'api/v1/') ? substr($uri, strlen('api/v1/')) : $uri;
    }
}
