<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\DataObject\Dtos\InvoiceDto;
use App\Service\InvoiceParsingService;
use PHPUnit\Framework\TestCase;

final class InvoiceParsingServiceTest extends TestCase
{
    private const EDI_EXAMPLE_FILE = __DIR__.'/edi_file_example/invoice.edi';
    
    private InvoiceParsingService $invoiceParsingService;

    protected function setUp(): void
    {
        $this->invoiceParsingService = new InvoiceParsingService();
    }

    public function testItParsesSimpleInvoicMessage(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV123456+9'\nMOA+79:1000,00'\nMOA+9:1210,00'\nTAX+7+VAT'\nUNT+7+1'";

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        self::assertInstanceOf(InvoiceDto::class, $invoice);
        self::assertSame('INV123456', $invoice->invoiceNumber);
        self::assertSame('380', $invoice->invoiceType);
        self::assertSame('1000.00', $invoice->totalBeforeTax);
        self::assertSame('1210.00', $invoice->totalAfterTax);
        self::assertSame('VAT', $invoice->taxType);
    }

    public function testItHandlesEmptyContent(): void
    {
        $invoice = $this->invoiceParsingService->parse('');

        self::assertNull($invoice);
    }

    public function testItParsesReferenceNumbers(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV999+9'\nRFF+ON:12345'\nRFF+DQ:67890'\nUNT+5+1'";

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        self::assertSame(12345, $invoice->orderId);
        self::assertSame(67890, $invoice->dispatchId);
    }

    public function testItHandlesDecimalFormats(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV111+9'\nMOA+79:5000,55'\nMOA+9:5750,63'\nTAX+VAT:20,00:750,63'\nUNT+6+1'";

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        self::assertSame('5000.55', $invoice->totalBeforeTax);
        self::assertSame('5750.63', $invoice->totalAfterTax);
        self::assertSame('750.63', $invoice->taxAmount);
    }

    public function testItPreservesNullValuesForMissingData(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV456+9'\nUNT+3+1'";

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        self::assertSame('INV456', $invoice->invoiceNumber);
        self::assertNull($invoice->orderId);
        self::assertNull($invoice->dispatchId);
        self::assertNull($invoice->totalBeforeTax);
        self::assertNull($invoice->totalAfterTax);
        self::assertNull($invoice->taxType);
        self::assertNull($invoice->taxAmount);
    }

    public function testItParsesRealWorldKrampDeutschlandInvoice(): void
    {
        // Real EDIFACT INVOIC D96A message from Kramp Deutschland
        $content = file_get_contents(self::EDI_EXAMPLE_FILE);
        self::assertIsString($content);

        $invoice = $this->invoiceParsingService->parse($content);

        self::assertNotNull($invoice);
        // Invoice header
        self::assertSame('18871152', $invoice->invoiceNumber);
        self::assertSame('380', $invoice->invoiceType);
        self::assertSame('VAT', $invoice->taxType);
        self::assertSame('25.00', $invoice->totalBeforeTax);
        self::assertSame('29.75', $invoice->totalAfterTax);
        self::assertSame('4.75', $invoice->taxAmount);
        
        // References
        self::assertSame(1529785353, $invoice->orderId);
        self::assertSame(3382238, $invoice->dispatchId);
        
        // Line items
        self::assertCount(1, $invoice->details);
        
        /** @var InvoiceDetailDto $lineItem */
        $lineItem = $invoice->details[0];
        self::assertSame('100100', $lineItem->itemCode);
        self::assertSame(1, $lineItem->quantity);
        self::assertSame('35.00', $lineItem->unitPrice);
    }
}
