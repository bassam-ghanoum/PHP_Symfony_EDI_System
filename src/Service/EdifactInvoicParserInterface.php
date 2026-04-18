<?php

declare(strict_types=1);

namespace App\Service;

interface EdifactInvoicParserInterface
{
    public function parse(string $filePath): EdifactInvoicParseResult;
}
