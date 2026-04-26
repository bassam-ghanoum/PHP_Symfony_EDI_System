<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dispatch')]
class Dispatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: IncomingFile::class)]
    #[ORM\JoinColumn(name: 'file_id', nullable: true, onDelete: 'SET NULL')]
    private ?IncomingFile $file = null;

    #[ORM\Column(length: 100, nullable: true, name: 'dispatcher_number')]
    private ?string $dispatcherNumber = null;

    #[ORM\Column(length: 255, nullable: true, name: 'order_number')]
    private ?string $orderNumber = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $shippingDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expectedArrival = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fileReceivedDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

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

    public function getDispatcherNumber(): ?string
    {
        return $this->dispatcherNumber;
    }

    public function setDispatcherNumber(?string $dispatcherNumber): self
    {
        $this->dispatcherNumber = $dispatcherNumber;

        return $this;
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

    public function getShippingDate(): ?\DateTimeImmutable
    {
        return $this->shippingDate;
    }

    public function setShippingDate(?\DateTimeImmutable $shippingDate): self
    {
        $this->shippingDate = $shippingDate;

        return $this;
    }

    public function getExpectedArrival(): ?\DateTimeImmutable
    {
        return $this->expectedArrival;
    }

    public function setExpectedArrival(?\DateTimeImmutable $expectedArrival): self
    {
        $this->expectedArrival = $expectedArrival;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

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
}
