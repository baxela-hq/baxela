<?php

namespace Modules\Core\Extensions;

use App\Baxela;
use Composer\InstalledVersions;
use Composer\Semver\Semver;

/**
 * Read-only view over vendor/composer/installed.json — where the
 * baxela:* commands learn a freshly required package's type, provider
 * and manifest without re-implementing composer internals.
 */
class ComposerPackages
{
    /**
     * The installed package record for a slug, or null when the
     * package is not present in this vendor tree.
     *
     * @return array<string, mixed>|null
     */
    public function package(string $slug): ?array
    {
        foreach ($this->installed() as $package) {
            if (($package['name'] ?? null) === $slug) {
                return $package;
            }
        }

        return null;
    }

    /**
     * The version composer reports for an installed package — the live
     * source of truth; nothing stores a copy. Null when the package is
     * not present (stale row, side-loaded and removed by hand).
     */
    public function version(string $slug): ?string
    {
        if (! InstalledVersions::isInstalled($slug)) {
            return null;
        }

        return InstalledVersions::getPrettyVersion($slug);
    }

    /**
     * The extension provider class declared in the package's
     * extra.laravel.providers, when it extends the base Extension.
     *
     * @return class-string<Extension>|null
     */
    public function providerFor(string $slug): ?string
    {
        $provider = $this->package($slug)['extra']['laravel']['providers'][0] ?? null;

        if ($provider === null || ! is_a($provider, Extension::class, true)) {
            return null;
        }

        return $provider;
    }

    /**
     * The package's extra.baxela manifest, when present.
     *
     * @return array<string, mixed>|null
     */
    public function manifestFor(string $slug): ?array
    {
        $manifest = $this->package($slug)['extra']['baxela'] ?? null;

        return is_array($manifest) ? $manifest : null;
    }

    /**
     * Whether the installed package's compat.core constraint is
     * satisfied by this core.
     */
    public function compatible(string $slug): bool
    {
        $constraint = (string) ($this->manifestFor($slug)['compat']['core'] ?? '');

        return $constraint !== '' && Semver::satisfies(Baxela::VERSION, $constraint);
    }

    /**
     * Whether the package declares itself a baxela extension — the
     * install gate; anything else is refused and rolled back.
     */
    public function isExtensionPackage(string $slug): bool
    {
        return ($this->package($slug)['type'] ?? null) === 'baxela-extension';
    }

    /**
     * Every installed package declaring itself a baxela extension —
     * including side-loaded ones no baxela: command has adopted yet.
     *
     * @return list<string>
     */
    public function extensionSlugs(): array
    {
        $slugs = [];

        foreach ($this->installed() as $package) {
            if (($package['type'] ?? null) === 'baxela-extension') {
                $slugs[] = (string) $package['name'];
            }
        }

        return $slugs;
    }

    /** @return list<array<string, mixed>> */
    protected function installed(): array
    {
        $path = base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return [];
        }

        $installed = json_decode((string) file_get_contents($path), true);

        $packages = $installed['packages'] ?? $installed;

        return is_array($packages) ? array_values($packages) : [];
    }
}
