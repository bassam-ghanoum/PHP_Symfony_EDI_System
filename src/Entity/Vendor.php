<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vendor')]
class Vendor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $internalId = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    /** @var Collection<int, Brand> */
    #[ORM\OneToMany(mappedBy: 'vendor', targetEntity: Brand::class)]
    private Collection $brands;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(mappedBy: 'vendor', targetEntity: Product::class)]
    private Collection $products;

    /** @var Collection<int, Item> */
    #[ORM\OneToMany(mappedBy: 'vendor', targetEntity: Item::class)]
    private Collection $items;

    public function __construct()
    {
        $this->brands = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInternalId(): ?string
    {
        return $this->internalId;
    }

    public function setInternalId(?string $internalId): self
    {
        $this->internalId = $internalId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /** @return Collection<int, Brand> */
    public function getBrands(): Collection
    {
        return $this->brands;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /** @return Collection<int, Item> */
    public function getItems(): Collection
    {
        return $this->items;
    }
}
