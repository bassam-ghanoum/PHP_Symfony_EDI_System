<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\DataObject\Dtos\InvoiceDto;
use App\Exception\MessagingException;
use App\Message\ImportedFileMessage;
use App\MessageHandler\ImportedFileMessageHandler;
use App\DataObject\EdifactInvoicParseResult;
use App\Parser\EdifactInvoicParserInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ImportedFileMessageHandlerTest extends TestCase
{
    private const NON_INVOIC_FILE_PATH = '/ImportedFiles/imported/sample_order.edi';
    private const INVOIC_FILE_PATH = '/ImportedFiles/imported/sample_invoice.edi';
    private const NOT_INVOIC_MESSAGE = 'Imported file is not an EDIFACT INVOIC D96A message. Parsing stopped.';
    private const INVOIC_DETECTED_MESSAGE = 'EDIFACT INVOIC D96A message detected. Parsing process started.';
    private const FILE_PATH_CONTEXT_KEY = 'file_path';
    private const NEXT_STEP_CONTEXT_KEY = 'next_step';
    private const NEXT_STEP_MESSAGE = 'Continue INVOIC data processing and persistence.';
    private const INVOICE_NUMBER_CONTEXT_KEY = 'invoice_number';
    private const INVOICE_DETAILS_CONTEXT_KEY = 'invoice_details_count';

    public function testItLogsAndStopsWhenTheImportedFileIsNotAnInvoicMessage(): void
    {
        $parseResult = new EdifactInvoicParseResult(
            filePath: self::NON_INVOIC_FILE_PATH,
            isInvoic: false,
            message: self::NOT_INVOIC_MESSAGE,
        );

        $parser = $this->createMock(EdifactInvoicParserInterface::class);
        $parser
            ->expects(self::once())
            ->method('parse')
            ->with(self::NON_INVOIC_FILE_PATH)
            ->willReturn($parseResult);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                $parseResult->message,
                [self::FILE_PATH_CONTEXT_KEY => self::NON_INVOIC_FILE_PATH],
            );

        $handler = new ImportedFileMessageHandler($parser, $logger);
        $handler(new ImportedFileMessage(self::NON_INVOIC_FILE_PATH));
    }

    public function testItLogsTheNextParsingStepWhenTheImportedFileIsAnInvoicMessage(): void
    {
        $parseResult = new EdifactInvoicParseResult(
            filePath: self::INVOIC_FILE_PATH,
            isInvoic: true,
            message: self::INVOIC_DETECTED_MESSAGE,
        );

        $parser = $this->createMock(EdifactInvoicParserInterface::class);
        $parser
            ->expects(self::once())
            ->method('parse')
            ->with(self::INVOIC_FILE_PATH)
            ->willReturn($parseResult);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                $parseResult->message,
                [
                    self::FILE_PATH_CONTEXT_KEY => self::INVOIC_FILE_PATH,
                    self::NEXT_STEP_CONTEXT_KEY => self::NEXT_STEP_MESSAGE,
                ],
            );

        $handler = new ImportedFileMessageHandler($parser, $logger);
        $handler(new ImportedFileMessage(self::INVOIC_FILE_PATH));
    }

    public function testItLogsInvoiceDataWhenParsed(): void
    {
        $invoice = InvoiceDto::create(
            invoiceNumber: 'INV123456',
            invoiceType: '380',
            totalBeforeTax: '1000.00',
            totalAfterTax: '1210.00',
            details: [
                InvoiceDetailDto::create(itemCode: 'ITEM001'),
                InvoiceDetailDto::create(itemCode: 'ITEM002'),
            ]
        );
        $parseResult = new EdifactInvoicParseResult(
            filePath: self::INVOIC_FILE_PATH,
            isInvoic: true,
            message: self::INVOIC_DETECTED_MESSAGE,
            invoice: $invoice,
        );

        $parser = $this->createMock(EdifactInvoicParserInterface::class);
        $parser
            ->expects(self::once())
            ->method('parse')
            ->with(self::INVOIC_FILE_PATH)
            ->willReturn($parseResult);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                $parseResult->message,
                [
                    self::FILE_PATH_CONTEXT_KEY => self::INVOIC_FILE_PATH,
                    self::NEXT_STEP_CONTEXT_KEY => self::NEXT_STEP_MESSAGE,
                    self::INVOICE_NUMBER_CONTEXT_KEY => 'INV123456',
                    self::INVOICE_DETAILS_CONTEXT_KEY => 2,
                ],
            );

        $handler = new ImportedFileMessageHandler($parser, $logger);
        $handler(new ImportedFileMessage(self::INVOIC_FILE_PATH));
    }

    public function testItWrapsParsingFailuresInMessagingException(): void
    {
        $parser = $this->createMock(EdifactInvoicParserInterface::class);
        $parser
            ->expects(self::once())
            ->method('parse')
            ->with(self::INVOIC_FILE_PATH)
            ->willThrowException(new \RuntimeException('parser failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::never())
            ->method('info');

        $handler = new ImportedFileMessageHandler($parser, $logger);

        $this->expectException(MessagingException::class);
        $this->expectExceptionMessage(self::INVOIC_FILE_PATH);

        $handler(new ImportedFileMessage(self::INVOIC_FILE_PATH));
    }
}
