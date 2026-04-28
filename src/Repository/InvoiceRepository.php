<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataObject\Dto\InvoiceDto;
use App\Entity\IncomingFile;
use App\Entity\Invoice;
use App\Exception\DatabaseException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function saveFromDto(InvoiceDto $dto): Invoice
    {
        try {
            $invoice = $dto->toEntity();

            if ($dto->incomingFile?->id !== null) {
                $invoice->setFile($this->getEntityManager()->getReference(IncomingFile::class, $dto->incomingFile->id));
            }

            $entityManager = $this->getEntityManager();
            $entityManager->persist($invoice);
            $entityManager->flush();

            return $invoice;
        } catch (\Throwable $e) {
            throw DatabaseException::fromThrowable($e, 'saving invoice from DTO');
        }
    }
}
