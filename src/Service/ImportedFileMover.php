<?php

declare(strict_types=1);

namespace App\Service;

use App\Dtos\IncomingFileDto;
use App\Entity\IncomingFile;
use App\Message\ImportedFileMessage;
use App\Repository\IncomingFileRepository;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ImportedFileMover
{
    private const INPUT_DIRECTORY_NAME = 'input';
    private const IMPORTED_DIRECTORY_NAME = 'imported';
    private const IMPORTED_FILE_STATUS = 'imported';
    private const PENDING_IMPORT_STATUS = 'pending_import';
    private const IMPORTED_FILE_URL_PREFIX = '/ImportedFiles/imported/';
    private const TIMESTAMP_FORMAT = 'Ymd_His';
    private const FILENAME_SEPARATOR = '_';
    private const UTF8_CHARSET = 'UTF-8';
    private const ASCII_TRANSLITERATION_CHARSET = 'ASCII//TRANSLIT//IGNORE';
    private const SANITIZE_FILENAME_PATTERN = '/[^A-Za-z0-9_-]+/';
    private const SANITIZE_FILENAME_REPLACEMENT = '_';
    private const FALLBACK_FILENAME = 'imported_file';
    private const CURRENT_DIRECTORY_NAME = '.';
    private const PARENT_DIRECTORY_NAME = '..';
    private const SUPPORTED_EXTENSIONS = ['txt', 'edi'];

    public function __construct(
        private Filesystem $filesystem,
        private MessageBusInterface $messageBus,
        private IncomingFileRepository $incomingFileRepository,
        private string $baseDirectory,
    ) {
    }

    public function __invoke(): ImportedFileMoveResult
    {
        return $this->moveFiles();
    }

    public function moveFiles(): ImportedFileMoveResult
    {
        $inputDirectory = $this->baseDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;
        $importedDirectory = $this->baseDirectory.\DIRECTORY_SEPARATOR.self::IMPORTED_DIRECTORY_NAME;

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
                ->setFileStatus(self::IMPORTED_FILE_STATUS)
                ->setUpdatedAt(new \DateTimeImmutable());
            $this->incomingFileRepository->update($incomingFile);

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
            if ($entry === self::CURRENT_DIRECTORY_NAME || $entry === self::PARENT_DIRECTORY_NAME) {
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
        $timestamp = (new \DateTimeImmutable())->format(self::TIMESTAMP_FORMAT);

        $timestampedFilename = $sanitizedBaseName.self::FILENAME_SEPARATOR.$timestamp;

        if ($extension !== '') {
            $timestampedFilename .= '.'.strtolower($extension);
        }

        return $timestampedFilename;
    }

    private function sanitizeFilename(string $filename): string
    {
        $transliteratedFilename = iconv(self::UTF8_CHARSET, self::ASCII_TRANSLITERATION_CHARSET, $filename);
        $normalizedFilename = $transliteratedFilename !== false ? $transliteratedFilename : $filename;
        $sanitizedFilename = preg_replace(self::SANITIZE_FILENAME_PATTERN, self::SANITIZE_FILENAME_REPLACEMENT, $normalizedFilename);

        if ($sanitizedFilename === null) {
            return self::FALLBACK_FILENAME;
        }

        $sanitizedFilename = trim($sanitizedFilename, '_-');

        return $sanitizedFilename !== '' ? $sanitizedFilename : self::FALLBACK_FILENAME;
    }

    private function createIncomingFileRecord(string $destinationPath, string $destinationFilename): IncomingFile
    {
        $now = new \DateTimeImmutable();
        $incomingFileDto = new IncomingFileDto(
            fileName: $destinationFilename,
            fileStatus: self::PENDING_IMPORT_STATUS,
            receivedAt: $now,
            createdAt: $now,
            updatedAt: $now,
            fileType: strtolower((string) pathinfo($destinationPath, \PATHINFO_EXTENSION)),
            fileUrl: self::IMPORTED_FILE_URL_PREFIX.$destinationFilename,
        );

        return $this->incomingFileRepository->save($incomingFileDto);
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
            $candidate = $name.self::FILENAME_SEPARATOR.$counter;

            if ($extension !== '') {
                $candidate .= '.'.$extension;
            }

            $destinationPath = $directory.\DIRECTORY_SEPARATOR.$candidate;
            ++$counter;
        } while (is_file($destinationPath));

        return $destinationPath;
    }
}
