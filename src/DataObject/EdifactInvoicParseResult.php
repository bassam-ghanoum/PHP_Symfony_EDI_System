<?php

declare(strict_types=1);

namespace App\DataObject;

use App\DataObject\Dtos\InvoiceDto;

final class EdifactInvoicParseResult
{
    public function __construct(
        public string $filePath,
        public bool $isInvoic,
        public string $message,
        public ?InvoiceDto $invoice = null,
    ) {
    }
}
