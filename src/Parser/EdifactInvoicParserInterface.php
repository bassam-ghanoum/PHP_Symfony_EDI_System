<?php

declare(strict_types=1);

namespace App\Parser;

use App\DataObject\EdifactInvoicParseResult;

interface EdifactInvoicParserInterface
{
    public function parse(string $filePath): EdifactInvoicParseResult;
}
