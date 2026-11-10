<?php

namespace Modules\Core\Contracts\Gateways\Order;

/**
 * Renders a document's neutral view to a PDF artifact (B.1 D12).
 * Exactly one core implementation today (mPDF, RTL-capable); future
 * country-specific document providers implement this same interface as
 * extensions — templates and engines stay behind it.
 */
interface DocumentRendererInterface
{
    public function render(DocumentView $view): PdfArtifact;
}
