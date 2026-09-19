<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'shop_order_item')]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column]
    private int $product_id = 0;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(length: 255)]
    private string $product_name = '';

    #[ORM\Column(length: 64)]
    private string $product_sku = '';

    #[ORM\Column(nullable: true)]
    private ?int $offer_id = null;

    #[ORM\Column(nullable: true)]
    private ?int $supplier_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplier_name = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $unit_price = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $line_total = '0.00';

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $product_snapshot = [];

    #[ORM\Column(type: 'string', length: 32, enumType: OrderItemStatus::class)]
    private OrderItemStatus $status = OrderItemStatus::Pending;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getProductId(): int
    {
        return $this->product_id;
    }

    public function setProductId(int $productId): static
    {
        $this->product_id = $productId;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);

        return $this;
    }

    public function getProductName(): string
    {
        return $this->product_name;
    }

    public function setProductName(string $productName): static
    {
        $this->product_name = $productName;

        return $this;
    }

    public function getProductSku(): string
    {
        return $this->product_sku;
    }

    public function setProductSku(string $productSku): static
    {
        $this->product_sku = $productSku;

        return $this;
    }

    public function getOfferId(): ?int
    {
        return $this->offer_id;
    }

    public function setOfferId(?int $offerId): static
    {
        $this->offer_id = $offerId;

        return $this;
    }

    public function getSupplierId(): ?int
    {
        return $this->supplier_id;
    }

    public function setSupplierId(?int $supplierId): static
    {
        $this->supplier_id = $supplierId;

        return $this;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplier_name;
    }

    public function setSupplierName(?string $supplierName): static
    {
        $this->supplier_name = $supplierName !== null && trim($supplierName) !== '' ? trim($supplierName) : null;

        return $this;
    }

    public function getUnitPrice(): float
    {
        return (float) $this->unit_price;
    }

    public function setUnitPrice(float $unitPrice): static
    {
        $this->unit_price = number_format($unitPrice, 2, '.', '');

        return $this;
    }

    public function getLineTotal(): float
    {
        return (float) $this->line_total;
    }

    public function setLineTotal(float $lineTotal): static
    {
        $this->line_total = number_format($lineTotal, 2, '.', '');

        return $this;
    }

    /** @return array<string, mixed> */
    public function getProductSnapshot(): array
    {
        return $this->product_snapshot;
    }

    /** @param array<string, mixed> $productSnapshot */
    public function setProductSnapshot(array $productSnapshot): static
    {
        $this->product_snapshot = $productSnapshot;

        return $this;
    }

    public function getStatus(): OrderItemStatus
    {
        return $this->status;
    }

    public function setStatus(OrderItemStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s × %d', $this->product_name, $this->quantity);
    }
}
