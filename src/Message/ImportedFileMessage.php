<?php

declare(strict_types=1);

namespace App\Message;

final class ImportedFileMessage
{
    public function __construct(
        public string $filePath,
    ) {
    }
}
