<?php

declare(strict_types=1);

namespace App\DataObject\Dtos;

final class IncomingFileDto
{
    public function __construct(
        public ?int $id = null,
        public ?string $fileName = null,
        public ?string $fileStatus = null,
        public ?\DateTimeImmutable $receivedAt = null,
        public ?\DateTimeImmutable $createdAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        public ?string $fileType = null,
        public ?string $fileUrl = null,
    ) {
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function setFileStatus(?string $fileStatus): self
    {
        $this->fileStatus = $fileStatus;

        return $this;
    }
}
