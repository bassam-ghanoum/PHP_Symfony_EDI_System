<?php

declare(strict_types=1);

namespace App\Parser;

use App\DataObject\EdifactInvoicParseResult;
use App\Service\InvoicePersistenceService;
use App\Service\InvoiceParsingService;

final class EdifactInvoicParser implements EdifactInvoicParserInterface
{
    private const INVOIC_PATTERN = "/UNH\\+[^+']+\\+INVOIC:D:96A:UN'/i";
    private const FILE_NOT_FOUND_MESSAGE = 'Imported file "%s" was not found.';
    private const FILE_READ_ERROR_MESSAGE = 'Unable to read imported file "%s".';
    private const NOT_INVOIC_MESSAGE = 'Imported file is not an EDIFACT INVOIC D96A message. Parsing stopped.';
    private const INVOIC_DETECTED_MESSAGE = 'EDIFACT INVOIC D96A message detected. Parsing process started.';

    public function __construct(
        private InvoiceParsingService $invoiceParsingService,
        private InvoicePersistenceService $invoicePersistenceService,
    ) {
    }

    public function parse(string $filePath): EdifactInvoicParseResult
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException(\sprintf(self::FILE_NOT_FOUND_MESSAGE, $filePath));
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException(\sprintf(self::FILE_READ_ERROR_MESSAGE, $filePath));
        }

        if (!$this->isInvoicMessage($content)) {
            return new EdifactInvoicParseResult(
                filePath: $filePath,
                isInvoic: false,
                message: self::NOT_INVOIC_MESSAGE,
            );
        }

        $invoice = $this->invoiceParsingService->parse($content);
        $this->invoicePersistenceService->save($invoice);

        return new EdifactInvoicParseResult(
            filePath: $filePath,
            isInvoic: true,
            message: self::INVOIC_DETECTED_MESSAGE,
            invoice: $invoice,
        );
    }

    private function isInvoicMessage(string $content): bool
    {
        return preg_match(self::INVOIC_PATTERN, $content) === 1;
    }
}
