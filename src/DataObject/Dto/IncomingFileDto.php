<?php

declare(strict_types=1);

namespace App\DataObject\Dto;

use App\Entity\IncomingFile;

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
        public ?string $originalFileName = null,
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

    public function toEntity(): IncomingFile
    {
        $incomingFile = new IncomingFile();
        $incomingFile
            ->setFileName($this->fileName)
            ->setFileStatus($this->fileStatus)
            ->setReceivedAt($this->receivedAt)
            ->setCreatedAt($this->createdAt)
            ->setUpdatedAt($this->updatedAt)
            ->setFileType($this->fileType)
            ->setOriginalFileName($this->originalFileName);

        return $incomingFile;
    }

    public static function fromEntity(IncomingFile $incomingFile): self
    {
        return new self(
            id: $incomingFile->getId(),
            fileName: $incomingFile->getFileName(),
            fileStatus: $incomingFile->getFileStatus(),
            receivedAt: $incomingFile->getReceivedAt(),
            createdAt: $incomingFile->getCreatedAt(),
            updatedAt: $incomingFile->getUpdatedAt(),
            fileType: $incomingFile->getFileType(),
            originalFileName: $incomingFile->getOriginalFileName(),
        );
    }
}
