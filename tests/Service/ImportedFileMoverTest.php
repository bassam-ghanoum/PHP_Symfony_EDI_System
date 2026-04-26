<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DataObject\Dtos\IncomingFileDto;
use App\DataObject\ImportedFileProcessingResult;
use App\Entity\IncomingFile;
use App\Message\ImportedFileMessage;
use App\Repository\IncomingFileRepository;
use App\Service\ImportedFileMover;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportedFileMoverTest extends TestCase
{
    private const WORKSPACE_PREFIX = 'imported-file-mover-';
    private const INPUT_DIRECTORY_NAME = 'input';
    private const IMPORTED_DIRECTORY_NAME = 'imported';
    private const FIRST_TXT_FILENAME = 'first.txt';
    private const SECOND_TXT_FILENAME = 'second.txt';
    private const FIRST_TXT_CONTENT = 'first';
    private const SECOND_TXT_CONTENT = 'second';
    private const UNSAFE_TXT_SOURCE_FILENAME = 'note @ invoice!.txt';
    private const UNSAFE_TXT_CONTENT = 'text';
    private const UNSUPPORTED_FILENAME = 'image.jpeg';
    private const UNSUPPORTED_CONTENT = 'binary';
    private const EDI_SOURCE_FILENAME = 'invoice_sample.edi';
    private const EDI_EXPECTED_PATTERN = '/^invoice_sample_\d{8}_\d{6}\.edi$/';
    private const EDI_CONTENT = "UNH+3+INVOIC:D:96A:UN'\n";
    private const IMPORTED_STATUS = 'imported';
    private const EDI_FILE_TYPE = 'edi';
    private const FIRST_TXT_IMPORTED_PATTERN = '/^first_\d{8}_\d{6}\.txt$/';
    private const SECOND_TXT_IMPORTED_PATTERN = '/^second_\d{8}_\d{6}\.txt$/';

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

    public function testItCreatesDirectoriesAndMovesTxtFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::FIRST_TXT_FILENAME, self::FIRST_TXT_CONTENT);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::SECOND_TXT_FILENAME, self::SECOND_TXT_CONTENT);

        $persistedIncomingFiles = [];
        $incomingFile = new IncomingFile();
        
        $incomingFileRepository = $this->createMock(IncomingFileRepository::class);
        $incomingFileRepository
            ->expects(self::exactly(2))
            ->method('save')
            ->with(self::isInstanceOf(IncomingFileDto::class))
            ->willReturnCallback(static function (IncomingFileDto $dto) use (&$persistedIncomingFiles, $incomingFile): IncomingFile {
                $file = new IncomingFile();
                $file->setFileName($dto->fileName);
                $file->setFileStatus($dto->fileStatus);
                $file->setReceivedAt($dto->receivedAt);
                $file->setCreatedAt($dto->createdAt);
                $file->setUpdatedAt($dto->updatedAt);
                $file->setFileType($dto->fileType);
                $file->setOriginalFileName($dto->originalFileName);
                $persistedIncomingFiles[] = $file;
                return $file;
            });

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportedFileMessage))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            incomingFileRepository: $incomingFileRepository,
            baseDirectory: $this->workspaceDirectory,
        );

        $results = iterator_to_array(($mover)());
        $successResults = array_filter($results, static fn (ImportedFileProcessingResult $r) => $r->status === 'success');

        self::assertCount(4, $results); // 2 processing + 2 success
        self::assertCount(2, $successResults);
        self::assertCount(2, $persistedIncomingFiles);
        self::assertFileDoesNotExist($inputDirectory.\DIRECTORY_SEPARATOR.self::FIRST_TXT_FILENAME);
        self::assertFileDoesNotExist($inputDirectory.\DIRECTORY_SEPARATOR.self::SECOND_TXT_FILENAME);
        
        $successResultsArray = array_values($successResults);
        self::assertMatchesRegularExpression(self::FIRST_TXT_IMPORTED_PATTERN, $successResultsArray[0]->fileName);
        self::assertMatchesRegularExpression(self::SECOND_TXT_IMPORTED_PATTERN, $successResultsArray[1]->fileName);
        
        self::assertSame($successResultsArray[0]->fileName, $persistedIncomingFiles[0]->getFileName());
        self::assertSame($successResultsArray[1]->fileName, $persistedIncomingFiles[1]->getFileName());
        self::assertSame(self::FIRST_TXT_FILENAME, $persistedIncomingFiles[0]->getOriginalFileName());
        self::assertSame(self::SECOND_TXT_FILENAME, $persistedIncomingFiles[1]->getOriginalFileName());
        self::assertSame(self::IMPORTED_STATUS, $persistedIncomingFiles[0]->getFileStatus());
        self::assertSame(self::IMPORTED_STATUS, $persistedIncomingFiles[1]->getFileStatus());
    }

    public function testItRejectsUnsafeOriginalFileNames(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::UNSAFE_TXT_SOURCE_FILENAME, self::UNSAFE_TXT_CONTENT);
        
        $incomingFileRepository = $this->createMock(IncomingFileRepository::class);
        $incomingFileRepository
            ->expects(self::never())
            ->method('save');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::never())
            ->method('dispatch');

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            incomingFileRepository: $incomingFileRepository,
            baseDirectory: $this->workspaceDirectory,
        );

        $results = iterator_to_array(($mover)());
        $failedResults = array_filter($results, static fn (ImportedFileProcessingResult $r) => $r->status === 'failed');

        self::assertCount(2, $results);
        self::assertCount(1, $failedResults);
        
        $failedResultsArray = array_values($failedResults);
        self::assertSame(self::UNSAFE_TXT_SOURCE_FILENAME, $failedResultsArray[0]->fileName);
        self::assertStringContainsString('contains unsupported characters', (string) $failedResultsArray[0]->errorMessage);
        self::assertFileExists($inputDirectory.\DIRECTORY_SEPARATOR.self::UNSAFE_TXT_SOURCE_FILENAME);
    }

    public function testItIgnoresUnsupportedFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::UNSUPPORTED_FILENAME, self::UNSUPPORTED_CONTENT);

        $incomingFileRepository = $this->createMock(IncomingFileRepository::class);
        $incomingFileRepository
            ->expects(self::never())
            ->method('save');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::never())
            ->method('dispatch');

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            incomingFileRepository: $incomingFileRepository,
            baseDirectory: $this->workspaceDirectory,
        );

        $results = iterator_to_array(($mover)());

        self::assertCount(0, $results);
        self::assertFileExists($inputDirectory.\DIRECTORY_SEPARATOR.self::UNSUPPORTED_FILENAME);
    }

    public function testItAcceptsAndImportsEdiFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::EDI_SOURCE_FILENAME, self::EDI_CONTENT);

        $persistedIncomingFiles = [];
        
        $incomingFileRepository = $this->createMock(IncomingFileRepository::class);
        $incomingFileRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(IncomingFileDto::class))
            ->willReturnCallback(static function (IncomingFileDto $dto) use (&$persistedIncomingFiles): IncomingFile {
                $file = new IncomingFile();
                $file->setFileName($dto->fileName);
                $file->setFileStatus($dto->fileStatus);
                $file->setReceivedAt($dto->receivedAt);
                $file->setCreatedAt($dto->createdAt);
                $file->setUpdatedAt($dto->updatedAt);
                $file->setFileType($dto->fileType);
                $file->setOriginalFileName($dto->originalFileName);
                $persistedIncomingFiles[] = $file;
                return $file;
            });

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportedFileMessage))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            incomingFileRepository: $incomingFileRepository,
            baseDirectory: $this->workspaceDirectory,
        );

        $results = iterator_to_array(($mover)());
        $successResults = array_filter($results, static fn (ImportedFileProcessingResult $r) => $r->status === 'success');

        self::assertCount(2, $results); // 1 processing + 1 success
        self::assertCount(1, $successResults);
        self::assertCount(1, $persistedIncomingFiles);
        
        $successResultsArray = array_values($successResults);
        self::assertMatchesRegularExpression(self::EDI_EXPECTED_PATTERN, $successResultsArray[0]->fileName);
        self::assertSame(self::EDI_FILE_TYPE, $persistedIncomingFiles[0]->getFileType());
        self::assertSame($successResultsArray[0]->fileName, $persistedIncomingFiles[0]->getFileName());
        self::assertSame(self::EDI_SOURCE_FILENAME, $persistedIncomingFiles[0]->getOriginalFileName());
        self::assertFileDoesNotExist($inputDirectory.\DIRECTORY_SEPARATOR.self::EDI_SOURCE_FILENAME);
    }

    public function testItCreatesMissingDirectoriesWhenTheyDoNotExist(): void
    {
        $incomingFileRepository = $this->createMock(IncomingFileRepository::class);
        $incomingFileRepository
            ->expects(self::never())
            ->method('save');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::never())
            ->method('dispatch');

        $mover = new ImportedFileMover(
            filesystem: new Filesystem(),
            messageBus: $messageBus,
            incomingFileRepository: $incomingFileRepository,
            baseDirectory: $this->workspaceDirectory,
        );

        $results = iterator_to_array(($mover)());

        self::assertCount(0, $results); // No files to process
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;
        $importedDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::IMPORTED_DIRECTORY_NAME;
        self::assertDirectoryExists($inputDirectory);
        self::assertDirectoryExists($importedDirectory);
    }
}
