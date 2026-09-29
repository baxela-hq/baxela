<?php

namespace Modules\Catalog\Support\ProductImport;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Gateways\Media\DTOs\MediaDto;

/**
 * Minimal CSV reader for the product import: reads a stored media file,
 * strips the UTF-8 BOM Excel writes, sniffs the delimiter from the
 * header line (comma/semicolon/tab) and parses rows with PHP's native
 * fgetcsv so quoted commas, newlines and escapes survive.
 */
final class ProductImportCsv
{
    public const int ROW_CAP = 1000;

    public const string BOM = "\xEF\xBB\xBF";

    private const string DEFAULT_DELIMITER = ',';

    /**
     * @throws \RuntimeException when the stored file cannot be read
     */
    public static function readContent(MediaDto $media): string
    {
        $content = Storage::disk($media->disk)->get($media->path);

        if ($content === null) {
            throw new \RuntimeException("media file not readable: {$media->path}");
        }

        if (str_starts_with($content, self::BOM)) {
            // The BOM is one UTF-8 character but three bytes; strip bytes,
            // not characters, or a malformed tail leaks into the parser.
            $content = substr($content, strlen(self::BOM));
        }

        return $content;
    }

    /**
     * Pick the delimiter that occurs most often in the header line.
     */
    public static function sniffDelimiter(string $headerLine): string
    {
        $counts = [
            ',' => substr_count($headerLine, ','),
            ';' => substr_count($headerLine, ';'),
            "\t" => substr_count($headerLine, "\t"),
        ];

        $best = array_search(max($counts), $counts, true);

        return $best === false || $counts[$best] === 0 ? self::DEFAULT_DELIMITER : (string) $best;
    }

    /**
     * Parse CSV content into rows. Fully blank rows (trailing newlines
     * and the like) are dropped. The header stays rows[0].
     *
     * @return array{0: string, 1: array<int, array<int, string>>}
     */
    public static function parse(string $content): array
    {
        $firstNewline = strpos($content, "\n");
        $headerLine = $firstNewline === false
            ? $content
            : substr($content, 0, $firstNewline);
        $delimiter = self::sniffDelimiter(str_replace("\r", '', $headerLine));

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('unable to open csv stream');
        }

        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $cells = array_map(fn ($cell) => (string) $cell, $row);

            // A completely empty line parses as a single empty (or null)
            // cell; treat rows where every cell is blank as padding.
            if (trim(implode('', $cells)) === '') {
                continue;
            }

            $rows[] = $cells;
        }
        fclose($stream);

        return [$delimiter, $rows];
    }

    /**
     * Render rows back to CSV with Excel-friendly quoting.
     *
     * @param  array<int, array<int, string|null>>  $rows
     */
    public static function render(array $rows): string
    {
        $lines = array_map(
            fn (array $row) => implode(',', array_map(
                fn ($cell) => self::escapeCell((string) ($cell ?? '')),
                $row,
            )),
            $rows,
        );

        return implode("\n", $lines)."\n";
    }

    private static function escapeCell(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')
            || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
