<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataObject\Dtos\InvoiceDto;
use App\Entity\Invoice;
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
        $invoice = (new Invoice())
            ->setInvoiceNumber($dto->invoiceNumber)
            ->setOrderId($dto->orderId)
            ->setDispatchId($dto->dispatchId)
            ->setInvoiceType($dto->invoiceType)
            ->setTotalBeforeTax($dto->totalBeforeTax)
            ->setTotalAfterTax($dto->totalAfterTax)
            ->setTaxType($dto->taxType)
            ->setTaxAmount($dto->taxAmount)
            ->setFileReceivedDate($dto->fileReceivedDate);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($invoice);
        $entityManager->flush();

        return $invoice;
    }
}
