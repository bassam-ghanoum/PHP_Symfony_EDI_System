<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ImportedFileMover
{
    public function __construct(
        private Filesystem $filesystem,
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

        foreach ($this->listFiles($inputDirectory) as $sourcePath) {
            $destinationPath = $this->buildUniqueDestinationPath(
                $importedDirectory,
                basename($sourcePath),
            );

            $this->filesystem->rename($sourcePath, $destinationPath, true);
            $movedFiles[] = basename($destinationPath);
        }

        return new ImportedFileMoveResult(
            inputDirectory: $inputDirectory,
            importedDirectory: $importedDirectory,
            movedFiles: $movedFiles,
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

            if (is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
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
