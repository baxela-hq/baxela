<?php

namespace Modules\Order\Schemas\Document;

/**
 * PDF artifact lifecycle (D7), shared by invoices and credit notes: the
 * legal record is the row, the PDF is a regenerable representation.
 * pending_generation → generating → ready | generation_failed; the
 * generating claim carries a lease so a crashed worker cannot wedge the
 * document.
 */
enum DocumentPdfStatusEnum: string
{
    case PENDING_GENERATION = 'pending_generation';

    case GENERATING = 'generating';

    case READY = 'ready';

    case GENERATION_FAILED = 'generation_failed';
}
