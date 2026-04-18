<?php

declare(strict_types=1);

namespace App\Service;

final class EdifactInvoicParser
{
    private const INVOIC_PATTERN = "/UNH\\+[^+']+\\+INVOIC:D:96A:UN'/i";

    public function parse(string $filePath): EdifactInvoicParseResult
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException(\sprintf('Imported file "%s" was not found.', $filePath));
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException(\sprintf('Unable to read imported file "%s".', $filePath));
        }

        if (!$this->isInvoicMessage($content)) {
            return new EdifactInvoicParseResult(
                filePath: $filePath,
                isInvoic: false,
                message: 'Imported file is not an EDIFACT INVOIC D96A message. Parsing stopped.',
            );
        }

        return new EdifactInvoicParseResult(
            filePath: $filePath,
            isInvoic: true,
            message: 'EDIFACT INVOIC D96A message detected. Parsing process started.',
        );
    }

    private function isInvoicMessage(string $content): bool
    {
        return preg_match(self::INVOIC_PATTERN, $content) === 1;
    }
}
