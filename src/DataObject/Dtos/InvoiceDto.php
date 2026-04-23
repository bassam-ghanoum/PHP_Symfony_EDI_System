<?php

declare(strict_types=1);

namespace App\DataObject\Dtos;

/**
 * @psalm-immutable
 */
final class InvoiceDto
{
    /**
     * @param InvoiceDetailDto[] $details
     */
    public function __construct(
        public ?string $invoiceNumber = null,
        public ?int $orderId = null,
        public ?int $dispatchId = null,
        public ?string $invoiceType = null,
        public ?string $totalBeforeTax = null,
        public ?string $totalAfterTax = null,
        public ?string $taxType = null,
        public ?string $taxAmount = null,
        public ?int $fileId = null,
        public ?\DateTimeImmutable $fileReceivedDate = null,
        public array $details = [],
    ) {
    }

    /**
     * @param InvoiceDetailDto[] $details
     */
    public static function create(
        ?string $invoiceNumber = null,
        ?int $orderId = null,
        ?int $dispatchId = null,
        ?string $invoiceType = null,
        ?string $totalBeforeTax = null,
        ?string $totalAfterTax = null,
        ?string $taxType = null,
        ?string $taxAmount = null,
        ?int $fileId = null,
        ?\DateTimeImmutable $fileReceivedDate = null,
        array $details = [],
    ): self {
        return new self(
            invoiceNumber: $invoiceNumber,
            orderId: $orderId,
            dispatchId: $dispatchId,
            invoiceType: $invoiceType,
            totalBeforeTax: $totalBeforeTax,
            totalAfterTax: $totalAfterTax,
            taxType: $taxType,
            taxAmount: $taxAmount,
            fileId: $fileId,
            fileReceivedDate: $fileReceivedDate,
            details: $details,
        );
    }

    public function toEntity(): \App\Entity\Invoice
    {
        $invoice = new \App\Entity\Invoice();
        $invoice
            ->setInvoiceNumber($this->invoiceNumber)
            ->setOrderId($this->orderId)
            ->setDispatchId($this->dispatchId)
            ->setInvoiceType($this->invoiceType)
            ->setTotalBeforeTax($this->totalBeforeTax)
            ->setTotalAfterTax($this->totalAfterTax)
            ->setTaxType($this->taxType)
            ->setTaxAmount($this->taxAmount)
            ->setFileReceivedDate($this->fileReceivedDate);

        return $invoice;
    }
}
