<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'item')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Brand $brand = null;

    #[ORM\ManyToOne(targetEntity: Vendor::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Vendor $vendor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ean = null;

    /** @var Collection<int, InvoiceDetail> */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: InvoiceDetail::class)]
    private Collection $invoiceDetails;

    public function __construct()
    {
        $this->invoiceDetails = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getVendor(): ?Vendor
    {
        return $this->vendor;
    }

    public function setVendor(?Vendor $vendor): self
    {
        $this->vendor = $vendor;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): self
    {
        $this->ean = $ean;

        return $this;
    }

    /** @return Collection<int, InvoiceDetail> */
    public function getInvoiceDetails(): Collection
    {
        return $this->invoiceDetails;
    }
}
