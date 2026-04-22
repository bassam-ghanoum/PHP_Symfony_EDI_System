<?php

declare(strict_types=1);

namespace App\Tests\Parser;

use App\DataObject\Dtos\InvoiceDto;
use App\Parser\EdifactInvoicParser;
use App\Service\InvoicePersistenceService;
use App\Service\InvoiceParsingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class EdifactInvoicParserTest extends TestCase
{
    private const WORKSPACE_PREFIX = 'edifact-invoic-parser-';
    private const EDI_EXAMPLE_FILE = __DIR__.'/../Service/edi_file_example/invoice.edi';
    private const NON_INVOIC_CONTENT = "UNH+3+ORDERS:D:96A:UN'\nBGM+220+12345+9'";
    private const INVOIC_CONTENT = "UNA:+.? '\nUNH+3+INVOIC:D:96A:UN'\nBGM+380+18871152+9'\nMOA+79:1000,00'\nMOA+9:1210,00'\nTAX+7+VAT'";
    private const NOT_INVOIC_MESSAGE = 'Imported file is not an EDIFACT INVOIC D96A message. Parsing stopped.';
    private const INVOIC_DETECTED_MESSAGE = 'EDIFACT INVOIC D96A message detected. Parsing process started.';
    private const IMPORTED_FILE_MESSAGE_FRAGMENT = 'Imported file';
    private const MISSING_FILE_NAME = 'missing.edi';
    private const IMPORTED_FILE_NAME = 'invoice.txt';

    private string $workspaceDirectory;
    private EdifactInvoicParser $parser;

    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.self::WORKSPACE_PREFIX.bin2hex(random_bytes(8));
        $this->parser = new EdifactInvoicParser(
            new InvoiceParsingService(),
            $this->createStub(InvoicePersistenceService::class),
        );
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->workspaceDirectory)) {
            $filesystem->remove($this->workspaceDirectory);
        }
    }

    public function testItStopsWhenImportedFileIsNotAnInvoicMessage(): void
    {
        $filePath = $this->createImportedFile(self::NON_INVOIC_CONTENT);

        $result = $this->parser->parse($filePath);

        self::assertFalse($result->isInvoic);
        self::assertSame(self::NOT_INVOIC_MESSAGE, $result->message);
        self::assertNull($result->invoice);
    }

    public function testItRecognizesAnInvoicMessageAndStartsTheProcess(): void
    {
        $filePath = $this->createImportedFile(self::INVOIC_CONTENT);

        $result = $this->parser->parse($filePath);

        self::assertTrue($result->isInvoic);
        self::assertSame(self::INVOIC_DETECTED_MESSAGE, $result->message);
        self::assertNotNull($result->invoice);
        self::assertInstanceOf(InvoiceDto::class, $result->invoice);
    }

    public function testItParsesInvoiceData(): void
    {
        $filePath = $this->createImportedFile(self::INVOIC_CONTENT);

        $result = $this->parser->parse($filePath);

        self::assertTrue($result->isInvoic);
        self::assertNotNull($result->invoice);
        
        $invoice = $result->invoice;
        self::assertSame('18871152', $invoice->invoiceNumber);
        self::assertSame('380', $invoice->invoiceType);
        self::assertSame('1000.00', $invoice->totalBeforeTax);
        self::assertSame('1210.00', $invoice->totalAfterTax);
        self::assertSame('VAT', $invoice->taxType);
    }

    public function testItThrowsWhenTheImportedFileDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::IMPORTED_FILE_MESSAGE_FRAGMENT);

        $this->parser->parse($this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::MISSING_FILE_NAME);
    }

    public function testItParsesInvoiceWithLineItems(): void
    {
        $content = "UNA:+.? '\nUNH+1+INVOIC:D:96A:UN'\nBGM+380+INV789+9'\nLIN+1+ITEM001'\nQTY+1:100'\nPRI+AAB:10,00'\nMOA+79:1000,00'\nMOA+9:1210,00'\nUNT+8+1'";
        $filePath = $this->createImportedFile($content);

        $result = $this->parser->parse($filePath);

        self::assertTrue($result->isInvoic);
        self::assertNotNull($result->invoice);
        self::assertCount(1, $result->invoice->details);
    }

    public function testItParsesRealWorldKrampDeutschlandInvoice(): void
    {
        // Real EDIFACT INVOIC D96A message from Kramp Deutschland
        $content = file_get_contents(self::EDI_EXAMPLE_FILE);
        self::assertIsString($content);
        $filePath = $this->createImportedFile($content);

        $result = $this->parser->parse($filePath);

        self::assertTrue($result->isInvoic);
        self::assertNotNull($result->invoice);
        
        $invoice = $result->invoice;
        self::assertSame('18871152', $invoice->invoiceNumber);
        self::assertSame('380', $invoice->invoiceType);
        self::assertSame('25.00', $invoice->totalBeforeTax);
        self::assertSame('29.75', $invoice->totalAfterTax);
        self::assertSame('4.75', $invoice->taxAmount);
        self::assertSame(1529785353, $invoice->orderId);
        self::assertSame(3382238, $invoice->dispatchId);
        self::assertCount(1, $invoice->details);
    }

    private function createImportedFile(string $content): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->workspaceDirectory);

        $filePath = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::IMPORTED_FILE_NAME;
        file_put_contents($filePath, $content);

        return $filePath;
    }
}
