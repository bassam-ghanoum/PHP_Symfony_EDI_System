<?php

declare(strict_types=1);

namespace App\Service;

use App\DataObject\Dto\IncomingFileDto;
use App\DataObject\ImportedFileProcessingResult;
use App\Exception\FileImportException;
use App\Exception\MessagingException;
use App\Message\ImportedFileMessage;
use App\Repository\IncomingFileRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportedFileMover
{
    private const INPUT_DIRECTORY_NAME = 'input';
    private const IMPORTED_DIRECTORY_NAME = 'imported';
    private const ERROR_DIRECTORY_NAME = 'error';
    private const IMPORTED_FILE_STATUS = 'imported';
    private const PENDING_IMPORT_STATUS = 'pending_import';
    private const TIMESTAMP_FORMAT = 'Ymd_His';
    private const FILENAME_SEPARATOR = '_';
    private const UTF8_CHARSET = 'UTF-8';
    private const ASCII_TRANSLITERATION_CHARSET = 'ASCII//TRANSLIT//IGNORE';
    private const SANITIZE_FILENAME_PATTERN = '/[^A-Za-z0-9_-]+/';
    private const SANITIZE_FILENAME_REPLACEMENT = '_';
    private const VALID_ORIGINAL_FILENAME_PATTERN = '/\A[A-Za-z0-9._-]+\z/';
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

public function __invoke(): \Generator
    {
        return $this->moveFiles();
    }

    /**
     * @return \Generator<int, ImportedFileProcessingResult>
     */
    public function moveFiles(): \Generator
    {
        $inputDirectory = $this->baseDirectory.\DIRECTORY_SEPARATOR.self::INPUT_DIRECTORY_NAME;
        $importedDirectory = $this->baseDirectory.\DIRECTORY_SEPARATOR.self::IMPORTED_DIRECTORY_NAME;

        $this->filesystem->mkdir([$inputDirectory, $importedDirectory]);

        foreach ($this->listFiles($inputDirectory) as $sourcePath) {
            $filename = basename($sourcePath);
            
            try {
                yield new ImportedFileProcessingResult(
                    filePath: $sourcePath,
                    fileName: $filename,
                    status: 'processing',
                );

                $this->assertOriginalFilenameIsSafe($filename);

                $destinationFilename = $this->buildTimestampedFilename($filename);
                $destinationPath = $this->buildUniqueDestinationPath(
                    $importedDirectory,
                    $destinationFilename,
                );

                $incomingFileDto = $this->createIncomingFileDto(
                    $destinationPath,
                    basename($destinationPath),
                    $filename,
                );

                $this->filesystem->rename($sourcePath, $destinationPath, true);

                $incomingFileDto
                    ->setFileStatus(self::IMPORTED_FILE_STATUS)
                    ->setUpdatedAt(new \DateTimeImmutable());
                
                $this->incomingFileRepository->save($incomingFileDto);

                try {
                    $this->dispatchImportedFileMessage($destinationPath);
                } catch (MessagingException $exception) {
                    $errorPath = $this->moveFileToErrorDirectory($destinationPath);

                    yield new ImportedFileProcessingResult(
                        filePath: $errorPath,
                        fileName: basename($errorPath),
                        status: 'failed',
                        errorMessage: $exception->getMessage(),
                    );

                    continue;
                }

                yield new ImportedFileProcessingResult(
                    filePath: $destinationPath,
                    fileName: basename($destinationPath),
                    status: 'success',
                );
            } catch (FileImportException $exception) {
                yield new ImportedFileProcessingResult(
                    filePath: $sourcePath,
                    fileName: $filename,
                    status: 'failed',
                    errorMessage: $exception->getMessage(),
                );
            } catch (\Throwable $e) {
                $fileImportException = new FileImportException(
                    \sprintf('Failed to process file "%s".', $filename),
                    previous: $e,
                );

                trigger_error(
                    \sprintf('Failed to process file "%s": %s', $filename, $fileImportException->getPrevious()?->getMessage() ?? $fileImportException->getMessage()),
                    \E_USER_WARNING,
                );

                yield new ImportedFileProcessingResult(
                    filePath: $sourcePath,
                    fileName: $filename,
                    status: 'failed',
                    errorMessage: $fileImportException->getPrevious()?->getMessage() ?? $fileImportException->getMessage(),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function listFiles(string $directory): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            throw new FileImportException(\sprintf('Unable to read directory "%s".', $directory));
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

    private function createIncomingFileDto(
        string $destinationPath,
        string $destinationFilename,
        string $originalFilename,
    ): IncomingFileDto
    {
        $now = new \DateTimeImmutable();

        return new IncomingFileDto(
            fileName: $destinationFilename,
            fileStatus: self::PENDING_IMPORT_STATUS,
            receivedAt: $now,
            createdAt: $now,
            updatedAt: $now,
            fileType: strtolower((string) pathinfo($destinationPath, \PATHINFO_EXTENSION)),
            originalFileName: $originalFilename,
        );
    }

    private function assertOriginalFilenameIsSafe(string $filename): void
    {
        if (preg_match(self::VALID_ORIGINAL_FILENAME_PATTERN, $filename) !== 1) {
            throw new FileImportException(
                \sprintf('Original filename "%s" contains unsupported characters.', $filename),
            );
        }
    }

    private function dispatchImportedFileMessage(string $destinationPath): void
    {
        try {
            $this->messageBus->dispatch(new ImportedFileMessage($destinationPath));
        } catch (\Throwable $throwable) {
            throw new MessagingException(
                \sprintf('Unable to dispatch import message for "%s".', basename($destinationPath)),
                previous: $throwable,
            );
        }
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

    private function moveFileToErrorDirectory(string $filePath): string
    {
        $errorDirectory = $this->baseDirectory.DIRECTORY_SEPARATOR.self::ERROR_DIRECTORY_NAME;
        $this->filesystem->mkdir($errorDirectory);

        $destinationPath = $this->buildUniqueDestinationPath($errorDirectory, basename($filePath));
        $this->filesystem->rename($filePath, $destinationPath);

        return $destinationPath;
    }
}
