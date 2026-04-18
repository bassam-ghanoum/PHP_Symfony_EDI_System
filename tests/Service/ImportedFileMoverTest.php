<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\IncomingFile;
use App\Message\ImportedFileMessage;
use App\Service\ImportedFileMover;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportedFileMoverTest extends TestCase
{
    private const WORKSPACE_PREFIX = 'imported-file-mover-';
    private const INPUT_DIRECTORY_NAME = 'input';
    private const FIRST_TXT_FILENAME = 'first.txt';
    private const SECOND_TXT_FILENAME = 'second.txt';
    private const FIRST_TXT_CONTENT = 'first';
    private const SECOND_TXT_CONTENT = 'second';
    private const SANITIZED_TXT_SOURCE_FILENAME = 'note @ invoice!.txt';
    private const SANITIZED_TXT_EXPECTED_PATTERN = '/^note_invoice_\d{8}_\d{6}\.txt$/';
    private const SANITIZED_TXT_CONTENT = 'text';
    private const UNSUPPORTED_FILENAME = 'image.jpeg';
    private const UNSUPPORTED_CONTENT = 'binary';
    private const EDI_SOURCE_FILENAME = 'invoice sample.edi';
    private const EDI_EXPECTED_PATTERN = '/^invoice_sample_\d{8}_\d{6}\.edi$/';
    private const EDI_CONTENT = "UNH+3+INVOIC:D:96A:UN'\n";
    private const IMPORTED_FILE_URL_PREFIX = '/ImportedFiles/imported/';
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
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::exactly(2))
            ->method('persist')
            ->with(self::callback(static function (object $entity) use (&$persistedIncomingFiles): bool {
                if (!$entity instanceof IncomingFile) {
                    return false;
                }

                $persistedIncomingFiles[] = $entity;

                return true;
            }));
        $entityManager
            ->expects(self::exactly(4))
            ->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportedFileMessage))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            entityManager: $entityManager,
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(2, $result->movedCount());
        self::assertCount(2, $result->movedFilePaths);
        self::assertCount(2, $persistedIncomingFiles);
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.self::FIRST_TXT_FILENAME);
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.self::SECOND_TXT_FILENAME);
        self::assertMatchesRegularExpression(self::FIRST_TXT_IMPORTED_PATTERN, $result->movedFiles[0]);
        self::assertMatchesRegularExpression(self::SECOND_TXT_IMPORTED_PATTERN, $result->movedFiles[1]);
        self::assertFileExists($result->movedFilePaths[0]);
        self::assertFileExists($result->movedFilePaths[1]);
        self::assertSame($result->movedFiles[0], $persistedIncomingFiles[0]->getFileName());
        self::assertSame($result->movedFiles[1], $persistedIncomingFiles[1]->getFileName());
        self::assertSame(self::IMPORTED_FILE_URL_PREFIX.$result->movedFiles[0], $persistedIncomingFiles[0]->getFileUrl());
        self::assertSame(self::IMPORTED_FILE_URL_PREFIX.$result->movedFiles[1], $persistedIncomingFiles[1]->getFileUrl());
        self::assertSame(self::IMPORTED_STATUS, $persistedIncomingFiles[0]->getFileStatus());
        self::assertSame(self::IMPORTED_STATUS, $persistedIncomingFiles[1]->getFileStatus());
    }

    public function testItIgnoresUnsupportedFilesAndSanitizesTheImportedTxtName(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::SANITIZED_TXT_SOURCE_FILENAME, self::SANITIZED_TXT_CONTENT);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::UNSUPPORTED_FILENAME, self::UNSUPPORTED_CONTENT);

        $persistedIncomingFiles = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (object $entity) use (&$persistedIncomingFiles): bool {
                if (!$entity instanceof IncomingFile) {
                    return false;
                }

                $persistedIncomingFiles[] = $entity;

                return true;
            }));
        $entityManager
            ->expects(self::exactly(2))
            ->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportedFileMessage))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            entityManager: $entityManager,
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(1, $result->movedCount());
        self::assertMatchesRegularExpression(self::SANITIZED_TXT_EXPECTED_PATTERN, $result->movedFiles[0]);
        self::assertSame($result->movedFiles[0], $persistedIncomingFiles[0]->getFileName());
        self::assertSame(self::IMPORTED_FILE_URL_PREFIX.$result->movedFiles[0], $persistedIncomingFiles[0]->getFileUrl());
        self::assertFileExists($result->movedFilePaths[0]);
        self::assertFileExists($result->inputDirectory.\DIRECTORY_SEPARATOR.self::UNSUPPORTED_FILENAME);
    }

    public function testItAcceptsAndImportsEdiFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.self::EDI_SOURCE_FILENAME, self::EDI_CONTENT);

        $persistedIncomingFiles = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (object $entity) use (&$persistedIncomingFiles): bool {
                if (!$entity instanceof IncomingFile) {
                    return false;
                }

                $persistedIncomingFiles[] = $entity;

                return true;
            }));
        $entityManager
            ->expects(self::exactly(2))
            ->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportedFileMessage))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            messageBus: $messageBus,
            entityManager: $entityManager,
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(1, $result->movedCount());
        self::assertMatchesRegularExpression(self::EDI_EXPECTED_PATTERN, $result->movedFiles[0]);
        self::assertSame(self::EDI_FILE_TYPE, $persistedIncomingFiles[0]->getFileType());
        self::assertSame($result->movedFiles[0], $persistedIncomingFiles[0]->getFileName());
        self::assertSame(self::IMPORTED_FILE_URL_PREFIX.$result->movedFiles[0], $persistedIncomingFiles[0]->getFileUrl());
        self::assertFileExists($result->movedFilePaths[0]);
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.self::EDI_SOURCE_FILENAME);
    }

    public function testItCreatesMissingDirectoriesWhenTheyDoNotExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::never())
            ->method('persist');
        $entityManager
            ->expects(self::never())
            ->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus
            ->expects(self::never())
            ->method('dispatch');

        $mover = new ImportedFileMover(
            filesystem: new Filesystem(),
            messageBus: $messageBus,
            entityManager: $entityManager,
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(0, $result->movedCount());
        self::assertDirectoryExists($result->inputDirectory);
        self::assertDirectoryExists($result->importedDirectory);
    }
}
