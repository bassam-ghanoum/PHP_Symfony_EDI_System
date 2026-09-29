<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Message\ImportedFileMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class ImportedFileFailureSubscriber implements EventSubscriberInterface
{
    private const ERROR_DIRECTORY_NAME = 'error';
    private const FILENAME_SEPARATOR = '_';

    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof ImportedFileMessage || $event->willRetry() || !is_file($message->filePath)) {
            return;
        }

        $errorDirectory = dirname(dirname($message->filePath)).DIRECTORY_SEPARATOR.self::ERROR_DIRECTORY_NAME;
        $this->filesystem->mkdir($errorDirectory);
        $destinationPath = $this->getAvailableDestinationPath($errorDirectory, basename($message->filePath));

        $this->filesystem->rename($message->filePath, $destinationPath);
    }

    private function getAvailableDestinationPath(string $directory, string $filename): string
    {
        $destinationPath = $directory.DIRECTORY_SEPARATOR.$filename;

        if (!file_exists($destinationPath)) {
            return $destinationPath;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 1;

        do {
            $candidate = $name.self::FILENAME_SEPARATOR.$counter;

            if ($extension !== '') {
                $candidate .= '.'.$extension;
            }

            $destinationPath = $directory.DIRECTORY_SEPARATOR.$candidate;
            ++$counter;
        } while (file_exists($destinationPath));

        return $destinationPath;
    }
}