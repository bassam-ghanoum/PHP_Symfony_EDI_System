<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataObject\Dtos\IncomingFileDto;
use App\Entity\IncomingFile;
use App\Exception\DatabaseException;
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
        try {
            $incomingFile = $dto->toEntity();

            $this->getEntityManager()->persist($incomingFile);
            $this->getEntityManager()->flush();

            return $incomingFile;
        } catch (\Throwable $e) {
            throw DatabaseException::fromThrowable($e, 'saving incoming file');
        }
    }

    public function findOneByFileName(string $fileName): ?IncomingFile
    {
        try {
            return $this->findOneBy(['fileName' => $fileName]);
        } catch (\Throwable $e) {
            throw DatabaseException::fromThrowable($e, 'finding incoming file by name');
        }
    }
}
