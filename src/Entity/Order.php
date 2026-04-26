<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true, name: 'order_number')]
    private ?string $orderNumber = null;

    #[ORM\ManyToOne(targetEntity: Brand::class)]
    #[ORM\JoinColumn(name: 'brand_id', nullable: false)]
    private ?Brand $brand = null;

    #[ORM\ManyToOne(targetEntity: Buyer::class)]
    #[ORM\JoinColumn(name: 'buyer_id', nullable: true, onDelete: 'SET NULL')]
    private ?Buyer $buyer = null;

    #[ORM\ManyToOne(targetEntity: IncomingFile::class)]
    #[ORM\JoinColumn(name: 'file_id', nullable: true, onDelete: 'SET NULL')]
    private ?IncomingFile $file = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $orderDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fileReceivedDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expectedDelivery = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $season = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

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

    public function getBuyer(): ?Buyer
    {
        return $this->buyer;
    }

    public function setBuyer(?Buyer $buyer): self
    {
        $this->buyer = $buyer;

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

    public function getOrderDate(): ?\DateTimeImmutable
    {
        return $this->orderDate;
    }

    public function setOrderDate(?\DateTimeImmutable $orderDate): self
    {
        $this->orderDate = $orderDate;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpectedDelivery(): ?\DateTimeImmutable
    {
        return $this->expectedDelivery;
    }

    public function setExpectedDelivery(?\DateTimeImmutable $expectedDelivery): self
    {
        $this->expectedDelivery = $expectedDelivery;

        return $this;
    }

    public function getSeason(): ?string
    {
        return $this->season;
    }

    public function setSeason(?string $season): self
    {
        $this->season = $season;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }
}
