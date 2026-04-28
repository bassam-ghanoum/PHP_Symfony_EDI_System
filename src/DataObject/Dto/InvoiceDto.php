<?php

declare(strict_types=1);

namespace App\DataObject\Dto;

/**
 * Immutable data transfer object for invoice data.
 * Use the create() factory method or constructor to instantiate.
 */
final class InvoiceDto
{
    /**
     * @param InvoiceDetailDto[] $details
     */
    public function __construct(
        public readonly ?string $invoiceNumber = null,
        public readonly ?int $orderId = null,
        public readonly ?int $dispatchId = null,
        public readonly ?string $invoiceType = null,
        public readonly ?string $totalBeforeTax = null,
        public readonly ?string $totalAfterTax = null,
        public readonly ?string $taxType = null,
        public readonly ?string $taxAmount = null,
        public readonly ?IncomingFileDto $incomingFile = null,
        public readonly ?\DateTimeImmutable $fileReceivedDate = null,
        public readonly array $details = [],
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
        ?IncomingFileDto $incomingFile = null,
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
            incomingFile: $incomingFile,
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
