<?php

namespace Modules\Order\Documents;

use Modules\Core\Contracts\Gateways\Order\DocumentRendererInterface;
use Modules\Core\Contracts\Gateways\Order\DocumentView;
use Modules\Core\Contracts\Gateways\Order\PdfArtifact;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The one core PDF provider (B.1 D6/D12): Blade → HTML → mPDF. Pure
 * PHP (shared-hosting safe), RTL-capable — the fa/ar PoC gate passed
 * 2026-11-10 (shaping, mixed-direction, numerals, multi-page). Behind
 * the DocumentRendererInterface seam so country providers never touch
 * this class.
 */
class MpdfRenderer implements DocumentRendererInterface
{
    public const string TEMPLATE_VERSION = 'v1';

    public function render(DocumentView $view): PdfArtifact
    {
        $html = view('order::documents.document', ['doc' => $view])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $this->tempDir(),
            'direction' => $view->isRtl() ? 'rtl' : 'ltr',
        ]);

        $mpdf->WriteHTML($html);

        return new PdfArtifact(
            contents: $mpdf->Output('', Destination::STRING_RETURN),
            templateVersion: self::TEMPLATE_VERSION,
        );
    }

    protected function tempDir(): string
    {
        $dir = storage_path('framework/mpdf');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
