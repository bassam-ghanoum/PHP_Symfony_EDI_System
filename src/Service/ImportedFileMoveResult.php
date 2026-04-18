<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ImportedFileMoveResult
{
    /**
     * @param list<string> $movedFiles
     * @param list<string> $movedFilePaths
     */
    public function __construct(
        public string $inputDirectory,
        public string $importedDirectory,
        public array $movedFiles,
        public array $movedFilePaths,
    ) {
    }

    public function movedCount(): int
    {
        return \count($this->movedFiles);
    }
}
