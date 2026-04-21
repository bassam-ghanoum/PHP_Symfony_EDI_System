<?php

declare(strict_types=1);

namespace App\DataObject;

final readonly class ImportedFileProcessingResult
{
    public function __construct(
        public string $filePath,
        public string $fileName,
        public string $status,
        public ?string $errorMessage = null,
    ) {
    }
}
