<?php

declare(strict_types=1);

namespace App\DataObject;

final readonly class EdifactInvoicParseResult
{
    public function __construct(
        public string $filePath,
        public bool $isInvoic,
        public string $message,
    ) {
    }
}
