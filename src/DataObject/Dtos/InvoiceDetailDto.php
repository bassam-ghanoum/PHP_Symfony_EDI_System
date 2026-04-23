<?php

declare(strict_types=1);

namespace App\DataObject\Dtos;

/**
 * @psalm-immutable
 */
final class InvoiceDetailDto
{
    public function __construct(
        public ?string $itemCode = null,
        public ?string $itemDescription = null,
        public ?int $quantity = null,
        public ?string $unitPrice = null,
        public ?string $totalBeforeTax = null,
        public ?string $totalAfterTax = null,
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
