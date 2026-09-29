<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Modules\Catalog\Exceptions\Product\ImportFailedException;
use Modules\Catalog\Support\ProductImport\ProductImportCsv;
use Modules\Catalog\Support\ProductImport\ProductImportFields;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\DTOs\MediaDto;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use RuntimeException;

class PreviewProductImportAction
{
    public function __construct(
        protected MediaGatewayInterface $mediaGateway,
        protected CoreGatewayInterface $coreGateway,
    ) {}

    /**
     * Stream-parse a stored CSV: headers, sample rows, row count, a
     * suggested column mapping and the mappable field inventory.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function handle(int $mediaId): array
    {
        $media = $this->resolveCsv($mediaId);
        $languageCodes = $this->languageCodes();

        try {
            $content = ProductImportCsv::readContent($media);
        } catch (RuntimeException) {
            throw new ImportFailedException(meta: ['reason' => 'unreadable_file']);
        }

        [$delimiter, $rows] = ProductImportCsv::parse($content);

        if ($rows === []) {
            throw new ImportFailedException(meta: ['reason' => 'empty_file']);
        }

        $header = array_shift($rows);

        $warnings = [];
        $duplicates = array_diff_key($header, array_unique($header));
        foreach (array_count_values($duplicates) as $column => $count) {
            $warnings[] = "duplicate_header:{$column}";
        }

        $suggested = ProductImportFields::suggestMapping($header, $languageCodes);
        $suggestedMapping = [];
        foreach ($header as $index => $column) {
            // With duplicate header names the last occurrence wins; the
            // warning above tells the admin to fix the file regardless.
            $suggestedMapping[$column] = $suggested[$index];
            if ($suggested[$index] === null) {
                $warnings[] = "unmapped_column:{$column}";
            }
        }

        $rowCount = count($rows);
        if ($rowCount > ProductImportCsv::ROW_CAP) {
            $warnings[] = 'row_cap_exceeded';
        }

        return [
            'filename' => $media->name.($media->extension ? '.'.$media->extension : ''),
            'delimiter' => $delimiter,
            'headers' => array_values($header),
            'rowCount' => $rowCount,
            'sampleRows' => array_slice(array_values($rows), 0, 5),
            'suggestedMapping' => $suggestedMapping,
            'availableFields' => ProductImportFields::groups($languageCodes),
            'defaultLanguage' => $this->defaultLanguageCode(),
            'rowCap' => ProductImportCsv::ROW_CAP,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Resolve the media record and guard it behind the csv extension —
     * the historical application/vnd.ms-excel mime some browsers send
     * is only trusted when the extension matches.
     *
     * @throws ImportFailedException
     */
    protected function resolveCsv(int $mediaId): MediaDto
    {
        $media = $this->mediaGateway->findById($mediaId);

        if (! $media || mb_strtolower(trim((string) $media->extension)) !== 'csv') {
            throw new ImportFailedException(meta: ['reason' => 'invalid_media']);
        }

        return $media;
    }

    /**
     * @return array<int, string>
     */
    protected function languageCodes(): array
    {
        return $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->values()
            ->all();
    }

    protected function defaultLanguageCode(): string
    {
        $default = $this->coreGateway->getDefaultLanguage();

        if ($default?->code) {
            return $default->code;
        }

        return $this->languageCodes()[0] ?? 'en';
    }
}
