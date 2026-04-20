<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dtos\IncomingFileDto;
use App\Entity\IncomingFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class IncomingFileRepository  extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncomingFile::class);
    }

    public function save(IncomingFileDto $dto): IncomingFile
    {
        $incomingFile = $this->getIncomingFileFromDto($dto);

        $this->getEntityManager()->persist($incomingFile);
        $this->getEntityManager()->flush();

        return $incomingFile;
    }

    public function update(IncomingFile $incomingFile): void
    {
        $this->getEntityManager()->persist($incomingFile);
        $this->getEntityManager()->flush();
    }

    private function getIncomingFileFromDto(IncomingFileDto $dto): IncomingFile
    {
        $incomingFile = new IncomingFile();
        $incomingFile
            ->setFileName($dto->fileName)
            ->setFileStatus($dto->fileStatus)
            ->setReceivedAt($dto->receivedAt)
            ->setCreatedAt($dto->createdAt)
            ->setUpdatedAt($dto->updatedAt)
            ->setFileType($dto->fileType)
            ->setFileUrl($dto->fileUrl);

        return $incomingFile;
    }
}
