<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'shop_cart')]
#[ORM\HasLifecycleCallbacks]
class Cart
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $visitor_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $customer = null;

    #[ORM\Column(type: 'string', length: 32, enumType: CartStatus::class)]
    private CartStatus $status = CartStatus::Active;

    /** @var array<string, mixed>|null @deprecated legacy JSON storage */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $cart_data = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contact_data = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $delivery_data = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $order_data = null;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToOne(mappedBy: 'cart', targetEntity: ShipmentAddress::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ShipmentAddress $shipmentAddress = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitor_id;
    }

    public function setVisitorId(?string $visitorId): static
    {
        $this->visitor_id = $visitorId;

        return $this;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getStatus(): CartStatus
    {
        return $this->status;
    }

    public function setStatus(CartStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === CartStatus::Active;
    }

    /** @return Collection<int, CartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @return list<CartItem> */
    public function getActiveItems(): array
    {
        $active = [];
        foreach ($this->items as $item) {
            if ($item->isActive()) {
                $active[] = $item;
            }
        }

        return $active;
    }

    public function addItem(CartItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCart($this);
        }

        return $this;
    }

    public function removeItem(CartItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getCart() === $this) {
            $item->setCart(null);
        }

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getCartData(): ?array
    {
        return $this->cart_data;
    }

    /** @param array<string, mixed>|null $cartData */
    public function setCartData(?array $cartData): static
    {
        $this->cart_data = $cartData;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getContactData(): ?array
    {
        return $this->contact_data;
    }

    /** @param array<string, mixed>|null $contactData */
    public function setContactData(?array $contactData): static
    {
        $this->contact_data = $contactData;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getDeliveryData(): ?array
    {
        return $this->delivery_data;
    }

    /** @param array<string, mixed>|null $deliveryData */
    public function setDeliveryData(?array $deliveryData): static
    {
        $this->delivery_data = $deliveryData;

        return $this;
    }

    public function getShipmentAddress(): ?ShipmentAddress
    {
        return $this->shipmentAddress;
    }

    public function setShipmentAddress(?ShipmentAddress $shipmentAddress): static
    {
        if ($shipmentAddress !== null && $shipmentAddress->getCart() !== $this) {
            $shipmentAddress->setCart($this);
        }

        $this->shipmentAddress = $shipmentAddress;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getOrderData(): ?array
    {
        return $this->order_data;
    }

    /** @param array<string, mixed>|null $orderData */
    public function setOrderData(?array $orderData): static
    {
        $this->order_data = $orderData;

        return $this;
    }

    public function __toString(): string
    {
        $summary = $this->getCartSummaryLabel();
        if ($summary !== '—') {
            return sprintf('Cart #%d — %s', $this->id ?? 0, $summary);
        }

        $customer = $this->customer?->getEmail() ?? $this->visitor_id ?? 'guest';

        return sprintf('Cart #%d (%s)', $this->id ?? 0, $customer);
    }

    public function getCartSummaryLabel(): string
    {
        $activeItems = $this->getActiveItems();
        if ($activeItems === []) {
            if (\is_array($this->cart_data) && !empty($this->cart_data['product']) && \is_array($this->cart_data['product'])) {
                $name = (string) ($this->cart_data['product']['name'] ?? '');
                $quantity = (int) ($this->cart_data['quantity'] ?? 0);

                return $name !== '' && $quantity > 0
                    ? sprintf('%s × %d', $name, $quantity)
                    : '—';
            }

            return '—';
        }

        $parts = [];
        foreach ($activeItems as $item) {
            $parts[] = sprintf('%s × %d', $item->getProductName(), $item->getQuantity());
        }

        return implode(', ', $parts);
    }
}
