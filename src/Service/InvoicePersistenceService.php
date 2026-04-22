<?php

declare(strict_types=1);

namespace App\Service;

use App\DataObject\Dtos\InvoiceDto;
use App\Entity\Invoice;
use App\Repository\InvoiceDetailRepository;
use App\Repository\InvoiceRepository;

class InvoicePersistenceService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private InvoiceDetailRepository $invoiceDetailRepository,
    ) {
    }

    public function save(?InvoiceDto $invoiceDto): ?Invoice
    {
        if ($invoiceDto === null) {
            return null;
        }

        $invoice = $this->invoiceRepository->saveFromDto($invoiceDto);
        $this->invoiceDetailRepository->saveFromDtos($invoiceDto->details, $invoice);

        return $invoice;
    }
}
