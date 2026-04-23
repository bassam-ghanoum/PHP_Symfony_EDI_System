<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\DataObject\Dtos\InvoiceDto;
use App\Service\InvoiceParsingService;
use PHPUnit\Framework\TestCase;

final class InvoiceDetailParsingServiceTest extends TestCase
{
    private InvoiceParsingService $invoiceParsingService;

    protected function setUp(): void
    {
        $this->invoiceParsingService = new InvoiceParsingService();
    }

    public function testItParsesInvoiceWithLineItems(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV789+9'\nLIN+1+ITEM001'\nQTY+1:100'\nPRI+AAB:10,00'\nMOA+79:1000,00'\nLIN+2+ITEM002'\nQTY+1:50'\nPRI+AAB:20,00'\nMOA+79:1000,00'\nUNT+10+1'";

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        self::assertCount(2, $invoice->details);
        
        /** @var InvoiceDetailDto $firstDetail */
        $firstDetail = $invoice->details[0];
        self::assertSame('ITEM001', $firstDetail->itemCode);
        self::assertSame(100, $firstDetail->quantity);
        self::assertSame('10.00', $firstDetail->unitPrice);

        /** @var InvoiceDetailDto $secondDetail */
        $secondDetail = $invoice->details[1];
        self::assertSame('ITEM002', $secondDetail->itemCode);
        self::assertSame(50, $secondDetail->quantity);
        self::assertSame('20.00', $secondDetail->unitPrice);
    }

    public function testItHandlesMultipleLineItemsWithMixedData(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV222+9'\nLIN+1+PROD001'\nQTY+1:10'\nPRI+AAB:50,00'\nMOA+79:500,00'\nMOA+9:605,00'\nLIN+2+PROD002'\nQTY+1:5'\nMOA+79:250,00'\nMOA+9:302,50'\nUNT+11+1'";

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        self::assertCount(2, $invoice->details);
        
        /** @var InvoiceDetailDto $firstDetail */
        $firstDetail = $invoice->details[0];
        self::assertSame('500.00', $firstDetail->totalBeforeTax);
        self::assertSame('605.00', $firstDetail->totalAfterTax);

        /** @var InvoiceDetailDto $secondDetail */
        $secondDetail = $invoice->details[1];
        self::assertSame('250.00', $secondDetail->totalBeforeTax);
        self::assertSame('302.50', $secondDetail->totalAfterTax);
    }
}
