<?php

declare(strict_types=1);

namespace App\DataObject\Dto;

/**
 * Immutable data transfer object for invoice line items.
 * Use the create() factory method or constructor to instantiate.
 */
final class InvoiceDetailDto
{
    public function __construct(
        public readonly ?string $itemCode = null,
        public readonly ?string $itemDescription = null,
        public readonly ?int $quantity = null,
        public readonly ?string $unitPrice = null,
        public readonly ?string $totalBeforeTax = null,
        public readonly ?string $totalAfterTax = null,
    ) {
    }

    public static function create(
        ?string $itemCode = null,
        ?string $itemDescription = null,
        ?int $quantity = null,
        ?string $unitPrice = null,
        ?string $totalBeforeTax = null,
        ?string $totalAfterTax = null,
    ): self {
        return new self(
            itemCode: $itemCode,
            itemDescription: $itemDescription,
            quantity: $quantity,
            unitPrice: $unitPrice,
            totalBeforeTax: $totalBeforeTax,
            totalAfterTax: $totalAfterTax,
        );
    }

    public function toEntity(\App\Entity\Invoice $invoice): \App\Entity\InvoiceDetail
    {
        $invoiceDetail = new \App\Entity\InvoiceDetail();
        $invoiceDetail
            ->setInvoice($invoice)
            ->setQuantity($this->quantity)
            ->setUnitPrice($this->unitPrice)
            ->setTotalBeforeTax($this->totalBeforeTax)
            ->setTotalAfterTax($this->totalAfterTax);

        return $invoiceDetail;
    }
}
