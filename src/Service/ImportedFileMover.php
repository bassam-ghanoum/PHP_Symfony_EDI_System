<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\IncomingFile;
use App\Message\ImportedFileMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ImportedFileMover
{
    private const SUPPORTED_EXTENSIONS = ['txt', 'edi'];

    public function __construct(
        private Filesystem $filesystem,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager,
        private string $baseDirectory,
    ) {
    }

    public function __invoke(): ImportedFileMoveResult
    {
        return $this->moveFiles();
    }

    public function moveFiles(): ImportedFileMoveResult
    {
        $inputDirectory = $this->baseDirectory.\DIRECTORY_SEPARATOR.'input';
        $importedDirectory = $this->baseDirectory.\DIRECTORY_SEPARATOR.'imported';

        $this->filesystem->mkdir([$inputDirectory, $importedDirectory]);

        $movedFiles = [];
        $movedFilePaths = [];

        foreach ($this->listFiles($inputDirectory) as $sourcePath) {
            $destinationFilename = $this->buildTimestampedFilename(basename($sourcePath));
            $destinationPath = $this->buildUniqueDestinationPath(
                $importedDirectory,
                $destinationFilename,
            );
            $incomingFile = $this->createIncomingFileRecord($destinationPath, basename($destinationPath));

            $this->filesystem->rename($sourcePath, $destinationPath, true);

            $incomingFile
                ->setFileStatus('imported')
                ->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $movedFiles[] = basename($destinationPath);
            $movedFilePaths[] = $destinationPath;
            $this->messageBus->dispatch(new ImportedFileMessage($destinationPath));
        }

        return new ImportedFileMoveResult(
            inputDirectory: $inputDirectory,
            importedDirectory: $importedDirectory,
            movedFiles: $movedFiles,
            movedFilePaths: $movedFilePaths,
        );
    }

    /**
     * @return list<string>
     */
    private function listFiles(string $directory): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            throw new IOException(\sprintf('Unable to read directory "%s".', $directory));
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.\DIRECTORY_SEPARATOR.$entry;

            if (is_file($path) && $this->isSupportedImportedFile($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    private function isSupportedImportedFile(string $path): bool
    {
        return \in_array(strtolower((string) pathinfo($path, \PATHINFO_EXTENSION)), self::SUPPORTED_EXTENSIONS, true);
    }

    private function buildTimestampedFilename(string $filename): string
    {
        $extension = pathinfo($filename, \PATHINFO_EXTENSION);
        $baseName = pathinfo($filename, \PATHINFO_FILENAME);
        $sanitizedBaseName = $this->sanitizeFilename($baseName);
        $timestamp = (new \DateTimeImmutable())->format('Ymd_His');

        $timestampedFilename = $sanitizedBaseName.'_'.$timestamp;

        if ($extension !== '') {
            $timestampedFilename .= '.'.strtolower($extension);
        }

        return $timestampedFilename;
    }

    private function sanitizeFilename(string $filename): string
    {
        $transliteratedFilename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        $normalizedFilename = $transliteratedFilename !== false ? $transliteratedFilename : $filename;
        $sanitizedFilename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $normalizedFilename);

        if ($sanitizedFilename === null) {
            return 'imported_file';
        }

        $sanitizedFilename = trim($sanitizedFilename, '_-');

        return $sanitizedFilename !== '' ? $sanitizedFilename : 'imported_file';
    }

    private function createIncomingFileRecord(string $destinationPath, string $destinationFilename): IncomingFile
    {
        $now = new \DateTimeImmutable();
        $incomingFile = (new IncomingFile())
            ->setFileName($destinationFilename)
            ->setFileStatus('pending_import')
            ->setReceivedAt($now)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setFileType(strtolower((string) pathinfo($destinationPath, \PATHINFO_EXTENSION)))
            ->setFileUrl('/ImportedFiles/imported/'.$destinationFilename);

        $this->entityManager->persist($incomingFile);
        $this->entityManager->flush();

        return $incomingFile;
    }

    private function buildUniqueDestinationPath(string $directory, string $filename): string
    {
        $destinationPath = $directory.\DIRECTORY_SEPARATOR.$filename;

        if (!is_file($destinationPath)) {
            return $destinationPath;
        }

        $extension = pathinfo($filename, \PATHINFO_EXTENSION);
        $name = pathinfo($filename, \PATHINFO_FILENAME);
        $counter = 1;

        do {
            $candidate = $name.'_'.$counter;

            if ($extension !== '') {
                $candidate .= '.'.$extension;
            }

            $destinationPath = $directory.\DIRECTORY_SEPARATOR.$candidate;
            ++$counter;
        } while (is_file($destinationPath));

        return $destinationPath;
    }
}
