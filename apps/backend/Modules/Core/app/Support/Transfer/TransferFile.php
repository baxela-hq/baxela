<?php

namespace Modules\Core\Support\Transfer;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use RuntimeException;

/**
 * Reader for module transfer files: decodes the JSON and enforces the
 * envelope contract (format, module, version, well-formed sections).
 * Every failure throws a RuntimeException whose message is a stable
 * reason string the actions map onto the module exception. Section
 * *keys* are not validated here — they are module-defined, so the
 * actions check them against the owning module's declared sections.
 */
final class TransferFile
{
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

        if (($file[TransferFormat::KEY_FORMAT] ?? null) !== TransferFormat::FORMAT) {
            throw new RuntimeException('unknown_format');
        }

        if (! is_string($file[TransferFormat::KEY_MODULE] ?? null) || $file[TransferFormat::KEY_MODULE] === '') {
            throw new RuntimeException('missing_module');
        }

        if ((int) ($file[TransferFormat::KEY_VERSION] ?? 0) !== TransferFormat::VERSION) {
            throw new RuntimeException('unsupported_version');
        }

        if (! isset($file[TransferFormat::KEY_SECTIONS]) || ! is_array($file[TransferFormat::KEY_SECTIONS])) {
            throw new RuntimeException('missing_sections');
        }

        foreach ($file[TransferFormat::KEY_SECTIONS] as $section) {
            $entity = $section[TransferFormat::KEY_ENTITY] ?? null;
            if (! is_string($entity) || $entity === '') {
                throw new RuntimeException('invalid_section');
            }

            if (! is_array($section[TransferFormat::KEY_ROWS] ?? null)) {
                throw new RuntimeException('invalid_section:'.$entity);
            }
        }

        return $file;
    }

    /** @param array<string, mixed> $file */
    public static function module(array $file): string
    {
        return (string) $file[TransferFormat::KEY_MODULE];
    }

    /**
     * File sections keyed by entity, preserving the file's own order
     * (exporters emit sections in dependency order; the import path
     * re-orders against the module's declared order).
     *
     * @param  array<string, mixed>  $file
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function sections(array $file): array
    {
        $sections = [];
        foreach ($file[TransferFormat::KEY_SECTIONS] as $section) {
            $sections[$section[TransferFormat::KEY_ENTITY]] = $section[TransferFormat::KEY_ROWS];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $file
     * @return int<0, max>
     */
    public static function totalRows(array $file): int
    {
        $total = 0;
        foreach ($file[TransferFormat::KEY_SECTIONS] as $section) {
            $total += count($section[TransferFormat::KEY_ROWS]);
        }

        return $total;
    }

    /**
     * File sections re-ordered by the owning module's declared dependency
     * order; rejects section keys the module does not declare, so a
     * hand-made or foreign file fails fast instead of replaying rows in
     * an order the module cannot resolve.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, array<int, array<string, mixed>>>
     *
     * @throws RuntimeException with an `unknown_section:{key}` reason
     */
    public static function orderedSections(array $file, ModuleTransferInterface $transfer): array
    {
        $byEntity = self::sections($file);

        $declared = array_map(
            fn ($section): string => $section->key,
            $transfer->sections(),
        );

        foreach (array_keys($byEntity) as $key) {
            if (! in_array($key, $declared, true)) {
                throw new RuntimeException('unknown_section:'.$key);
            }
        }

        $ordered = [];
        foreach ($transfer->sections() as $section) {
            if (isset($byEntity[$section->key])) {
                $ordered[$section->key] = $byEntity[$section->key];
            }
        }

        return $ordered;
    }
}
