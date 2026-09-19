<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ProductOfferRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductOfferRepository::class)]
#[ORM\Table(name: 'shop_product_offer')]
#[ORM\HasLifecycleCallbacks]
class ProductOffer
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(name: 'supplier_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Supplier $supplier = null;

    #[ORM\Column(length: 64)]
    private string $sku = '';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $old_price = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $qty = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = trim($sku);

        return $this;
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = number_format(max(0, $price), 2, '.', '');

        return $this;
    }

    public function getOldPrice(): ?float
    {
        return $this->old_price !== null ? (float) $this->old_price : null;
    }

    public function setOldPrice(?float $oldPrice): static
    {
        $this->old_price = $oldPrice !== null
            ? number_format(max(0, $oldPrice), 2, '.', '')
            : null;

        return $this;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): static
    {
        $this->qty = max(0, $qty);

        return $this;
    }

    public function isInStock(): bool
    {
        return $this->qty > 0;
    }

    public function __toString(): string
    {
        return sprintf('%s — %s ₴', $this->sku, number_format($this->getPrice(), 2, '.', ' '));
    }
}
