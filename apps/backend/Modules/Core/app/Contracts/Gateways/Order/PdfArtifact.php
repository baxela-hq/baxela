<?php

namespace Modules\Core\Contracts\Gateways\Order;

/**
 * The rendered representation of a document (B.1 D12): bytes plus
 * identity, produced by a renderer, persisted by the separate storage
 * service. Readonly — a renderer only renders.
 */
final class PdfArtifact
{
    public function __construct(
        public readonly string $contents,
        public readonly string $mimeType = 'application/pdf',
        public readonly string $templateVersion = 'v1',
    ) {}
}
