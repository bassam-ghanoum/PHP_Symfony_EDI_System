<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataObject\Dto\IncomingFileDto;
use App\DataObject\Dto\InvoiceDto;
use App\Entity\Dispatch;
use App\Entity\IncomingFile;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Message\ImportedFileMessage;
use App\MessageHandler\ImportedFileMessageHandler;
use App\Parser\EdifactInvoicParser;
use App\Repository\DispatchRepository;
use App\Repository\IncomingFileRepository;
use App\Repository\OrderRepository;
use App\Service\ImportedFileMover;
use App\Service\InvoicePersistenceService;
use App\Service\InvoiceParsingService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class InvoiceImportProcessTest extends TestCase
{
    private const WORKSPACE_PREFIX = 'invoice-import-process-';
    private const INPUT_DIRECTORY_NAME = 'input';
    private const IMPORTED_FILE_ID = 77;
    private const EDI_EXAMPLE_FILE = __DIR__.'/../Service/edi_file_example/invoice.edi';
    private const INVOIC_DETECTED_MESSAGE = 'EDIFACT INVOIC D96A message detected. Parsing process started.';

    private string $workspaceDirectory;

    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.self::WORKSPACE_PREFIX.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->workspaceDirectory)) {
            $filesystem->remove($this->workspaceDirectory);
        }
    }

    public function testItCoversTheFullInvoiceImportAndParsingProcess(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;
        $filesystem->mkdir($inputDirectory);

        $content = file_get_contents(self::EDI_EXAMPLE_FILE);
        self::assertIsString($content);

        $sourcePath = $inputDirectory.\DIRECTORY_SEPARATOR.'kramp-invoice.edi';
        file_put_contents($sourcePath, $content);

        $capturedMessage = null;
        $savedIncomingFile = null;
        $persistedInvoiceDto = null;

        $incomingFileRepository = $this->createMock(IncomingFileRepository::class);
        $incomingFileRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(IncomingFileDto::class))
            ->willReturnCallback(function (IncomingFileDto $dto) use (&$savedIncomingFile): IncomingFile {
                $incomingFile = $dto->toEntity();
                $this->setEntityId($incomingFile, self::IMPORTED_FILE_ID);
                $savedIncomingFile = $incomingFile;

                return $incomingFile;
            });

        $incomingFileRepository
            ->expects(self::exactly(2))
            ->method('findOneByFileName')
            ->willReturnCallback(function (string $fileName) use (&$savedIncomingFile): ?IncomingFile {
                if ($savedIncomingFile === null) {
                    return null;
                }

                if ($fileName === $savedIncomingFile->getFileName()) {
                    return $savedIncomingFile;
                }

                return null;
            });

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (object $message) use (&$capturedMessage): bool {
                if (!$message instanceof ImportedFileMessage) {
                    return false;
                }

                $capturedMessage = $message;

                return true;
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            incomingFileRepository: $incomingFileRepository,
            baseDirectory: $this->workspaceDirectory,
        );

        $results = iterator_to_array(($mover)());
        self::assertCount(2, $results);
        self::assertInstanceOf(ImportedFileMessage::class, $capturedMessage);
        self::assertNotNull($savedIncomingFile);
        self::assertNotNull($savedIncomingFile->getFileName());
        self::assertNotSame($capturedMessage->filePath, $savedIncomingFile->getFileName());
        self::assertSame(basename($capturedMessage->filePath), $savedIncomingFile->getFileName());

        $orderRepository = $this->createStub(OrderRepository::class);
        $dispatchRepository = $this->createStub(DispatchRepository::class);

        $orderRepository
            ->method('findByOrderNumber')
            ->willReturnCallback(function (string $orderNumber): ?Order {
                return $orderNumber === '1529785353'
                    ? $this->createOrderWithId(1529785353, $orderNumber)
                    : null;
            });

        $dispatchRepository
            ->method('findByDispatcherNumberAndOrderNumber')
            ->willReturnCallback(function (string $dispatcherNumber, string $orderNumber): ?Dispatch {
                if ($dispatcherNumber !== '3382238' || $orderNumber !== '1529785353') {
                    return null;
                }

                return $this->createDispatchWithId(3382238, $dispatcherNumber, $orderNumber);
            });

        $invoicePersistenceService = $this->createMock(InvoicePersistenceService::class);
        $invoicePersistenceService
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (?InvoiceDto $invoiceDto) use (&$persistedInvoiceDto): bool {
                $persistedInvoiceDto = $invoiceDto;

                if ($invoiceDto === null) {
                    return false;
                }

                return $invoiceDto->invoiceNumber === '18871152'
                    && $invoiceDto->incomingFile?->id === self::IMPORTED_FILE_ID
                    && count($invoiceDto->details) === 1;
            }))
            ->willReturn(new Invoice());

        $parser = new EdifactInvoicParser(
            new InvoiceParsingService($orderRepository, $dispatchRepository),
            $invoicePersistenceService,
            $incomingFileRepository,
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with(
                self::INVOIC_DETECTED_MESSAGE,
                [
                    'file_path' => $capturedMessage->filePath,
                    'next_step' => 'Continue INVOIC data processing and persistence.',
                    'invoice_number' => '18871152',
                    'invoice_details_count' => 1,
                ],
            );

        $handler = new ImportedFileMessageHandler($parser, $logger);
        $handler($capturedMessage);

        self::assertNotNull($persistedInvoiceDto);
        self::assertSame(self::IMPORTED_FILE_ID, $persistedInvoiceDto->incomingFile?->id);
        self::assertSame('18871152', $persistedInvoiceDto->invoiceNumber);
        self::assertSame(1529785353, $persistedInvoiceDto->orderId);
        self::assertSame(3382238, $persistedInvoiceDto->dispatchId);
        self::assertCount(1, $persistedInvoiceDto->details);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty($entity, 'id');
        $reflectionProperty->setValue($entity, $id);
    }

    private function createOrderWithId(int $id, string $orderNumber): Order
    {
        $order = (new Order())
            ->setOrderNumber($orderNumber);

        $this->setEntityId($order, $id);

        return $order;
    }

    private function createDispatchWithId(int $id, string $dispatcherNumber, string $orderNumber): Dispatch
    {
        $dispatch = (new Dispatch())
            ->setDispatcherNumber($dispatcherNumber)
            ->setOrderNumber($orderNumber);

        $this->setEntityId($dispatch, $id);

        return $dispatch;
    }
}
