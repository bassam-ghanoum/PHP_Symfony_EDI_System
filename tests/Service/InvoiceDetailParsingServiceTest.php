<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataObject\Dto\InvoiceDetailDto;
use App\DataObject\Dto\IncomingFileDto;
use App\Repository\DispatchRepository;
use App\Repository\OrderRepository;
use App\Service\InvoiceParsingService;
use PHPUnit\Framework\TestCase;

final class InvoiceDetailParsingServiceTest extends TestCase
{
    private InvoiceParsingService $invoiceParsingService;

    protected function setUp(): void
    {
        $this->invoiceParsingService = new InvoiceParsingService(
            $this->createStub(OrderRepository::class),
            $this->createStub(DispatchRepository::class),
        );
    }

    public function testItParsesInvoiceWithLineItems(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV789+9'\nLIN+1+ITEM001'\nQTY+1:100'\nPRI+AAB:10,00'\nMOA+79:1000,00'\nLIN+2+ITEM002'\nQTY+1:50'\nPRI+AAB:20,00'\nMOA+79:1000,00'\nUNT+10+1'";

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

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

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

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

    public function testItParsesLineItemsFromPiaImdAndMoa203(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+9021568808'\nLIN+1'\nPIA+1+19:SA'\nPIA+5+2007130:SA'\nIMD+F++:::PostNord MyPack Collect'\nIMD+F++:::Sending_Country?:SE'\nQTY+47:1:PCE'\nMOA+203:10'\nPRI+AAA:10::::PCE'\nLIN+2'\nPIA+1+68:SA'\nPIA+5+1001091:SA'\nIMD+F++:::Notification by SMS'\nQTY+47:1:PCE'\nMOA+203:0'\nPRI+AAA:0::::PCE'\nUNT+16+1'";

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

        self::assertNotNull($invoice);
        self::assertCount(2, $invoice->details);

        /** @var InvoiceDetailDto $firstDetail */
        $firstDetail = $invoice->details[0];
        self::assertSame('2007130', $firstDetail->itemCode);
        self::assertSame('PostNord MyPack Collect', $firstDetail->itemDescription);
        self::assertSame(1, $firstDetail->quantity);
        self::assertSame('10', $firstDetail->unitPrice);
        self::assertSame('10', $firstDetail->totalBeforeTax);

        /** @var InvoiceDetailDto $secondDetail */
        $secondDetail = $invoice->details[1];
        self::assertSame('1001091', $secondDetail->itemCode);
        self::assertSame('Notification by SMS', $secondDetail->itemDescription);
        self::assertSame(1, $secondDetail->quantity);
        self::assertSame(null, $secondDetail->unitPrice);
        self::assertSame(null, $secondDetail->totalBeforeTax);
    }

    private function createIncomingFileDto(): IncomingFileDto
    {
        return new IncomingFileDto(
            id: 1,
            fileName: '/ImportedFiles/imported/sample_invoice.edi',
        );
    }
}
