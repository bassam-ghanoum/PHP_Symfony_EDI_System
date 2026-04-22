<?php

declare(strict_types=1);

namespace App\Repository;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\Entity\Invoice;
use App\Entity\InvoiceDetail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class InvoiceDetailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoiceDetail::class);
    }

    /**
     * @param InvoiceDetailDto[] $detailDtos
     */
    public function saveFromDtos(array $detailDtos, Invoice $invoice): void
    {
        $entityManager = $this->getEntityManager();

        foreach ($detailDtos as $detailDto) {
            $invoiceDetail = (new InvoiceDetail())
                ->setInvoice($invoice)
                ->setQuantity($detailDto->quantity)
                ->setUnitPrice($detailDto->unitPrice)
                ->setTotalBeforeTax($detailDto->totalBeforeTax)
                ->setTotalAfterTax($detailDto->totalAfterTax);

            $entityManager->persist($invoiceDetail);
        }

        $entityManager->flush();
    }
}
