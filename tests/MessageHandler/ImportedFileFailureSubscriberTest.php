<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\EventSubscriber\ImportedFileFailureSubscriber;
use App\Message\ImportedFileMessage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use PHPUnit\Framework\TestCase;

final class ImportedFileFailureSubscriberTest extends TestCase
{
    private Filesystem $filesystem;
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'edi-import-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryDirectory);
    }

    public function testItMovesFileToErrorDirectoryAfterFinalFailure(): void
    {
        $importedDirectory = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'ImportedFiles'.DIRECTORY_SEPARATOR.'imported';
        $errorDirectory = dirname($importedDirectory).DIRECTORY_SEPARATOR.'error';
        $this->filesystem->mkdir($importedDirectory);

        $sourcePath = $importedDirectory.DIRECTORY_SEPARATOR.'invoice.edi';
        $destinationPath = $errorDirectory.DIRECTORY_SEPARATOR.'invoice.edi';
        $this->filesystem->dumpFile($sourcePath, 'invalid invoice');

        $event = new WorkerMessageFailedEvent(
            new Envelope(new ImportedFileMessage($sourcePath)),
            'async',
            new \RuntimeException('invoice parsing failed'),
        );

        $subscriber = new ImportedFileFailureSubscriber($this->filesystem);
        $subscriber->onMessageFailed($event);

        self::assertFileDoesNotExist($sourcePath);
        self::assertFileExists($destinationPath);
    }

    public function testItKeepsFileInImportedDirectoryWhileFailureWillBeRetried(): void
    {
        $importedDirectory = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'ImportedFiles'.DIRECTORY_SEPARATOR.'imported';
        $errorDirectory = dirname($importedDirectory).DIRECTORY_SEPARATOR.'error';
        $this->filesystem->mkdir($importedDirectory);

        $sourcePath = $importedDirectory.DIRECTORY_SEPARATOR.'invoice.edi';
        $this->filesystem->dumpFile($sourcePath, 'invalid invoice');

        $event = new WorkerMessageFailedEvent(
            new Envelope(new ImportedFileMessage($sourcePath)),
            'async',
            new \RuntimeException('temporary parsing failure'),
        );
        $event->setForRetry();

        $subscriber = new ImportedFileFailureSubscriber($this->filesystem);
        $subscriber->onMessageFailed($event);

        self::assertFileExists($sourcePath);
        self::assertDirectoryDoesNotExist($errorDirectory);
    }
}