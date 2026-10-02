<?php

namespace Modules\Catalog\Actions\Admin\DataTransfer;

use Modules\Catalog\Exceptions\Data\ImportFailedException;
use Modules\Catalog\Support\DataTransfer\CatalogTransferFile;
use Modules\Catalog\Support\DataTransfer\CatalogTransferFormat;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Media\DTOs\MediaDto;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Core\Schemas\Language\LanguageSchema;
use RuntimeException;

class PreviewCatalogImportAction
{
    public function __construct(
        protected MediaGatewayInterface $mediaGateway,
        protected CoreGatewayInterface $coreGateway,
    ) {}

    /**
     * Structure check of an uploaded export file: envelope, per-section
     * row counts, the row cap and language codes the file uses that this
     * store does not have.
     *
     * @return array<string, mixed>
     *
     * @throws ImportFailedException
     */
    public function handle(int $mediaId): array
    {
        $media = $this->resolveJson($mediaId);

        try {
            $file = CatalogTransferFile::decode(CatalogTransferFile::readContent($media));
        } catch (RuntimeException $e) {
            throw new ImportFailedException(meta: ['reason' => $e->getMessage()]);
        }

        $sections = [];
        foreach (CatalogTransferFile::sections($file) as $entity => $rows) {
            $sections[] = [
                CatalogTransferFormat::KEY_ENTITY => $entity,
                'rows' => count($rows),
            ];
        }

        $totalRows = CatalogTransferFile::totalRows($file);

        $warnings = [];
        if ($totalRows > CatalogTransferFormat::ROW_CAP) {
            $warnings[] = 'row_cap_exceeded';
        }

        $activeCodes = $this->coreGateway->getActiveLanguages()
            ->pluck(LanguageSchema::CODE)
            ->all();
        foreach (CatalogTransferFile::languageCodes($file) as $code) {
            if (! in_array($code, $activeCodes, true)) {
                $warnings[] = "unknown_language:{$code}";
            }
        }

        return [
            'filename' => $media->name.($media->extension ? '.'.$media->extension : ''),
            'format' => $file[CatalogTransferFormat::KEY_FORMAT],
            'module' => $file[CatalogTransferFormat::KEY_MODULE],
            'version' => (int) $file[CatalogTransferFormat::KEY_VERSION],
            'exported_at' => $file[CatalogTransferFormat::KEY_EXPORTED_AT] ?? null,
            'sections' => $sections,
            'total_rows' => $totalRows,
            'row_cap' => CatalogTransferFormat::ROW_CAP,
            'warnings' => $warnings,
        ];
    }

    /**
     * @throws ImportFailedException
     */
    protected function resolveJson(int $mediaId): MediaDto
    {
        $media = $this->mediaGateway->findById($mediaId);

        if (! $media || mb_strtolower(trim((string) $media->extension)) !== 'json') {
            throw new ImportFailedException(meta: ['reason' => 'invalid_media']);
        }

        return $media;
    }
}
