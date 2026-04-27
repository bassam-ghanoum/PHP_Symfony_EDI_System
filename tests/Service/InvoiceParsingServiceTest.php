<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\DataObject\Dtos\IncomingFileDto;
use App\DataObject\Dtos\InvoiceDto;
use App\Entity\Dispatch;
use App\Entity\Order;
use App\Repository\DispatchRepository;
use App\Repository\OrderRepository;
use App\Service\InvoiceParsingService;
use PHPUnit\Framework\TestCase;

final class InvoiceParsingServiceTest extends TestCase
{
    private const EDI_EXAMPLE_FILE = __DIR__.'/edi_file_example/invoice.edi';
    
    private InvoiceParsingService $invoiceParsingService;
    private OrderRepository $orderRepository;
    private DispatchRepository $dispatchRepository;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createStub(OrderRepository::class);
        $this->dispatchRepository = $this->createStub(DispatchRepository::class);
        $this->configureReferenceRepositories();

        $this->invoiceParsingService = new InvoiceParsingService(
            $this->orderRepository,
            $this->dispatchRepository,
        );
    }

    public function testItParsesSimpleInvoicMessage(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV123456+9'\nMOA+79:1000,00'\nMOA+9:1210,00'\nTAX+7+VAT'\nUNT+7+1'";

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

        self::assertNotNull($invoice);
        self::assertInstanceOf(InvoiceDto::class, $invoice);
        self::assertSame('INV123456', $invoice->invoiceNumber);
        self::assertSame('Debit', $invoice->invoiceType);
        self::assertSame('1000.00', $invoice->totalBeforeTax);
        self::assertSame('1210.00', $invoice->totalAfterTax);
        self::assertSame('VAT', $invoice->taxType);
    }

    public function testItHandlesEmptyContent(): void
    {
        $invoice = $this->invoiceParsingService->parse('', $this->createIncomingFileDto());

        self::assertNull($invoice);
    }

    public function testItParsesReferenceNumbers(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV999+9'\nRFF+ON:12345'\nRFF+DQ:67890'\nUNT+5+1'";

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

        self::assertNotNull($invoice);
        self::assertSame(12345, $invoice->orderId);
        self::assertSame(67890, $invoice->dispatchId);
    }

    public function testItHandlesDecimalFormats(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV111+9'\nMOA+79:5000,55'\nMOA+9:5750,63'\nTAX+VAT:20,00:750,63'\nUNT+6+1'";

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

        self::assertNotNull($invoice);
        self::assertSame('5000.55', $invoice->totalBeforeTax);
        self::assertSame('5750.63', $invoice->totalAfterTax);
        self::assertSame('750.63', $invoice->taxAmount);
    }

    public function testItPreservesNullValuesForMissingData(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV456+9'\nUNT+3+1'";

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

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

        $invoice = $this->invoiceParsingService->parse($content, $this->createIncomingFileDto());

        self::assertNotNull($invoice);
        // Invoice header
        self::assertSame('18871152', $invoice->invoiceNumber);
        self::assertSame('Debit', $invoice->invoiceType);
        self::assertSame('VAT', $invoice->taxType);
        self::assertSame('25.00', $invoice->totalBeforeTax);
        self::assertSame('29.75', $invoice->totalAfterTax);
        self::assertSame('4.75', $invoice->taxAmount);
        self::assertSame(1, $invoice->incomingFile?->id);
        
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

    private function configureReferenceRepositories(): void
    {
        $this->orderRepository
            ->method('findByOrderNumber')
            ->willReturnCallback(function (string $orderNumber): ?Order {
                return match ($orderNumber) {
                    '12345' => $this->createOrderWithId(12345, $orderNumber),
                    '1529785353' => $this->createOrderWithId(1529785353, $orderNumber),
                    default => null,
                };
            });

        $this->dispatchRepository
            ->method('findByDispatcherNumberAndOrderNumber')
            ->willReturnCallback(function (string $dispatcherNumber, string $orderNumber): ?Dispatch {
                return match ($dispatcherNumber) {
                    '67890' => $this->createDispatchWithId(67890, $dispatcherNumber),
                    '3382238' => $this->createDispatchWithId(3382238, $dispatcherNumber),
                    default => null,
                };
            });
    }

    private function createOrderWithId(int $id, string $orderNumber): Order
    {
        $order = (new Order())
            ->setOrderNumber($orderNumber);

        $this->setEntityId($order, $id);

        return $order;
    }

    private function createDispatchWithId(int $id, string $dispatchNumber): Dispatch
    {
        $dispatch = (new Dispatch())
            ->setDispatcherNumber($dispatchNumber);

        $this->setEntityId($dispatch, $id);

        return $dispatch;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty($entity, 'id');
        $reflectionProperty->setValue($entity, $id);
    }

    private function createIncomingFileDto(): IncomingFileDto
    {
        return new IncomingFileDto(
            id: 1,
            fileName: '/ImportedFiles/imported/sample_invoice.edi',
        );
    }
}
