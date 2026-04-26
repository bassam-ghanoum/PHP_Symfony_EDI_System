<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id')]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true, name: 'invoice_number')]
    private ?string $invoiceNumber = null;

    #[ORM\Column(nullable: true, name: 'order_id')]
    private ?int $orderId = null;

    #[ORM\Column(nullable: true, name: 'dispatch_id')]
    private ?int $dispatchId = null;

    #[ORM\Column(length: 50, nullable: true, name: 'invoice_type')]
    private ?string $invoiceType = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $totalBeforeTax = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $totalAfterTax = null;

    #[ORM\Column(length: 50, nullable: true, name: 'tax_type')]
    private ?string $taxType = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true, name: 'tax_amount')]
    private ?string $taxAmount = null;

    #[ORM\ManyToOne(targetEntity: IncomingFile::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?IncomingFile $file = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fileReceivedDate = null;

    /** @var Collection<int, InvoiceDetail> */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceDetail::class)]
    private Collection $invoiceDetails;

    public function __construct()
    {
        $this->invoiceDetails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(?int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getDispatchId(): ?int
    {
        return $this->dispatchId;
    }

    public function setDispatchId(?int $dispatchId): self
    {
        $this->dispatchId = $dispatchId;

        return $this;
    }

    public function getInvoiceType(): ?string
    {
        return $this->invoiceType;
    }

    public function setInvoiceType(?string $invoiceType): self
    {
        $this->invoiceType = $invoiceType;

        return $this;
    }

    public function getTotalBeforeTax(): ?string
    {
        return $this->totalBeforeTax;
    }

    public function setTotalBeforeTax(?string $totalBeforeTax): self
    {
        $this->totalBeforeTax = $totalBeforeTax;

        return $this;
    }

    public function getTotalAfterTax(): ?string
    {
        return $this->totalAfterTax;
    }

    public function setTotalAfterTax(?string $totalAfterTax): self
    {
        $this->totalAfterTax = $totalAfterTax;

        return $this;
    }

    public function getTaxType(): ?string
    {
        return $this->taxType;
    }

    public function setTaxType(?string $taxType): self
    {
        $this->taxType = $taxType;

        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): self
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function getFile(): ?IncomingFile
    {
        return $this->file;
    }

    public function setFile(?IncomingFile $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getFileReceivedDate(): ?\DateTimeImmutable
    {
        return $this->fileReceivedDate;
    }

    public function setFileReceivedDate(?\DateTimeImmutable $fileReceivedDate): self
    {
        $this->fileReceivedDate = $fileReceivedDate;

        return $this;
    }

    /** @return Collection<int, InvoiceDetail> */
    public function getInvoiceDetails(): Collection
    {
        return $this->invoiceDetails;
    }
}
