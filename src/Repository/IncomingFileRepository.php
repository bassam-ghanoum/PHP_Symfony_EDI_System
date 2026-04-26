<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataObject\Dtos\IncomingFileDto;
use App\Entity\IncomingFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class IncomingFileRepository  extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IncomingFile::class);
    }

    public function save(IncomingFileDto $dto): IncomingFile
    {
        $incomingFile = $dto->toEntity();

        $this->getEntityManager()->persist($incomingFile);
        $this->getEntityManager()->flush();

        return $incomingFile;
    }

    public function findOneByFileName(string $fileName): ?IncomingFile
    {
        return $this->findOneBy(['fileName' => $fileName]);
    }
}
