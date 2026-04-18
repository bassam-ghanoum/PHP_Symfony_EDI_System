<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ImportedFileMessage
{
    public function __construct(
        public string $filePath,
    ) {
    }
}
