<?php

namespace Modules\Catalog\Support\DataTransfer;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Gateways\Media\DTOs\MediaDto;
use RuntimeException;

/**
 * Reader for the catalog module export file: fetches the stored media
 * file, decodes the JSON and enforces the envelope contract (format,
 * module, version, known sections in dependency order). Every failure
 * throws a RuntimeException whose message is a stable reason string the
 * actions map onto the module exception.
 */
final class CatalogTransferFile
{
    /**
     * @throws RuntimeException when the stored file cannot be read
     */
    public static function readContent(MediaDto $media): string
    {
        $content = Storage::disk($media->disk)->get($media->path);

        if ($content === null) {
            throw new RuntimeException('unreadable_file');
        }

        return $content;
    }

    /**
     * Decode and validate the envelope; returns the full file array.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException with a reason string on any violation
     */
    public static function decode(string $content): array
    {
        $file = json_decode($content, true);

        if (! is_array($file)) {
            throw new RuntimeException('invalid_json');
        }

        if (($file[CatalogTransferFormat::KEY_FORMAT] ?? null) !== CatalogTransferFormat::FORMAT) {
            throw new RuntimeException('unknown_format');
        }

        if (($file[CatalogTransferFormat::KEY_MODULE] ?? null) !== CatalogTransferFormat::MODULE) {
            throw new RuntimeException('unknown_module');
        }

        if ((int) ($file[CatalogTransferFormat::KEY_VERSION] ?? 0) !== CatalogTransferFormat::VERSION) {
            throw new RuntimeException('unsupported_version');
        }

        if (! isset($file[CatalogTransferFormat::KEY_SECTIONS]) || ! is_array($file[CatalogTransferFormat::KEY_SECTIONS])) {
            throw new RuntimeException('missing_sections');
        }

        foreach ($file[CatalogTransferFormat::KEY_SECTIONS] as $section) {
            $entity = $section[CatalogTransferFormat::KEY_ENTITY] ?? null;
            if (! in_array($entity, CatalogTransferFormat::sectionOrder(), true)) {
                throw new RuntimeException('unknown_section:'.(is_string($entity) ? $entity : 'n/a'));
            }

            if (! is_array($section[CatalogTransferFormat::KEY_ROWS] ?? null)) {
                throw new RuntimeException('invalid_section:'.$entity);
            }
        }

        return $file;
    }

    /**
     * Sections keyed by entity, in dependency order.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function sections(array $file): array
    {
        $byEntity = [];
        foreach ($file[CatalogTransferFormat::KEY_SECTIONS] as $section) {
            $byEntity[$section[CatalogTransferFormat::KEY_ENTITY]] = $section[CatalogTransferFormat::KEY_ROWS];
        }

        $ordered = [];
        foreach (CatalogTransferFormat::sectionOrder() as $entity) {
            if (isset($byEntity[$entity])) {
                $ordered[$entity] = $byEntity[$entity];
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $file
     */
    public static function totalRows(array $file): int
    {
        return array_sum(array_map(
            fn ($section) => count($section[CatalogTransferFormat::KEY_ROWS]),
            $file[CatalogTransferFormat::KEY_SECTIONS],
        ));
    }

    /**
     * Every language code referenced by any row of any section.
     *
     * @param  array<string, mixed>  $file
     * @return array<int, string>
     */
    public static function languageCodes(array $file): array
    {
        $codes = [];
        foreach (self::sections($file) as $rows) {
            foreach ($rows as $row) {
                foreach (['translations', 'seo'] as $key) {
                    foreach ($row[CatalogTransferFormat::KEY_PAYLOAD][$key] ?? [] as $item) {
                        if (is_string($item['language'] ?? null)) {
                            $codes[] = $item['language'];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($codes));
    }
}
