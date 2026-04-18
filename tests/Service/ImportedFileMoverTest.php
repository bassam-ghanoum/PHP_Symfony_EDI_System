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
    private string $workspaceDirectory;

    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'imported-file-mover-'.bin2hex(random_bytes(8));
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
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.'input';

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'first.txt', 'first');
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'second.txt', 'second');

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
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.'first.txt');
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.'second.txt');
        self::assertMatchesRegularExpression('/^first_\d{8}_\d{6}\.txt$/', $result->movedFiles[0]);
        self::assertMatchesRegularExpression('/^second_\d{8}_\d{6}\.txt$/', $result->movedFiles[1]);
        self::assertFileExists($result->movedFilePaths[0]);
        self::assertFileExists($result->movedFilePaths[1]);
        self::assertSame($result->movedFiles[0], $persistedIncomingFiles[0]->getFileName());
        self::assertSame($result->movedFiles[1], $persistedIncomingFiles[1]->getFileName());
        self::assertSame('/ImportedFiles/imported/'.$result->movedFiles[0], $persistedIncomingFiles[0]->getFileUrl());
        self::assertSame('/ImportedFiles/imported/'.$result->movedFiles[1], $persistedIncomingFiles[1]->getFileUrl());
        self::assertSame('imported', $persistedIncomingFiles[0]->getFileStatus());
        self::assertSame('imported', $persistedIncomingFiles[1]->getFileStatus());
    }

    public function testItIgnoresUnsupportedFilesAndSanitizesTheImportedTxtName(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.'input';

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'nöté @ invoice!.txt', 'text');
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'image.jpeg', 'binary');

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
        self::assertMatchesRegularExpression('/^note_invoice_\d{8}_\d{6}\.txt$/', $result->movedFiles[0]);
        self::assertSame($result->movedFiles[0], $persistedIncomingFiles[0]->getFileName());
        self::assertSame('/ImportedFiles/imported/'.$result->movedFiles[0], $persistedIncomingFiles[0]->getFileUrl());
        self::assertFileExists($result->movedFilePaths[0]);
        self::assertFileExists($result->inputDirectory.\DIRECTORY_SEPARATOR.'image.jpeg');
    }

    public function testItAcceptsAndImportsEdiFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.'input';

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'invoice sample.edi', "UNH+3+INVOIC:D:96A:UN'\n");

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
        self::assertMatchesRegularExpression('/^invoice_sample_\d{8}_\d{6}\.edi$/', $result->movedFiles[0]);
        self::assertSame('edi', $persistedIncomingFiles[0]->getFileType());
        self::assertSame($result->movedFiles[0], $persistedIncomingFiles[0]->getFileName());
        self::assertSame('/ImportedFiles/imported/'.$result->movedFiles[0], $persistedIncomingFiles[0]->getFileUrl());
        self::assertFileExists($result->movedFilePaths[0]);
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.'invoice sample.edi');
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
