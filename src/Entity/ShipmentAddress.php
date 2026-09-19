<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\ShipmentAddressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentAddressRepository::class)]
#[ORM\Table(name: 'shop_shipment_address')]
#[ORM\HasLifecycleCallbacks]
class ShipmentAddress
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, enumType: ShipmentAddressType::class)]
    private ShipmentAddressType $type = ShipmentAddressType::Courier;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $courier_address = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $np_city_ref = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $np_city_name = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $np_warehouse_ref = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $np_warehouse_name = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $is_default = false;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $delivery_cost = null;

    #[ORM\OneToOne(inversedBy: 'shipmentAddress', targetEntity: Cart::class)]
    #[ORM\JoinColumn(name: 'cart_id', referencedColumnName: 'id', nullable: true, unique: true, onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    #[ORM\OneToOne(inversedBy: 'shipmentAddress', targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, unique: true, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne(inversedBy: 'shipmentAddresses', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @param array<string, mixed> $data */
    public static function fromDeliveryArray(array $data): self
    {
        $address = new self();
        $address->applyDeliveryArray($data);

        return $address;
    }

    /** @param array<string, mixed> $data */
    public function applyDeliveryArray(array $data): static
    {
        $method = (string) ($data['deliveryMethod'] ?? ShipmentAddressType::Courier->value);
        $this->type = ShipmentAddressType::tryFrom($method) ?? ShipmentAddressType::Courier;
        $isNovaPoshta = in_array($method, [
            ShipmentAddressType::NovaPoshtaBranch->value,
            ShipmentAddressType::NovaPoshtaPostomat->value,
        ], true);

        $this->courier_address = $isNovaPoshta
            ? null
            : self::nullableString($data['courierAddress'] ?? null);
        $this->np_city_ref = $isNovaPoshta ? self::nullableString($data['npCityRef'] ?? null) : null;
        $this->np_city_name = $isNovaPoshta ? self::nullableString($data['npCityName'] ?? null) : null;
        $this->np_warehouse_ref = $isNovaPoshta ? self::nullableString($data['npWarehouseRef'] ?? null) : null;
        $this->np_warehouse_name = $isNovaPoshta ? self::nullableString($data['npWarehouseName'] ?? null) : null;
        $this->setDeliveryCost(self::nullableFloat($data['deliveryCost'] ?? null));

        return $this;
    }

    public function cloneAsNew(): self
    {
        $clone = new self();
        $clone->type = $this->type;
        $clone->courier_address = $this->courier_address;
        $clone->np_city_ref = $this->np_city_ref;
        $clone->np_city_name = $this->np_city_name;
        $clone->np_warehouse_ref = $this->np_warehouse_ref;
        $clone->np_warehouse_name = $this->np_warehouse_name;
        $clone->label = $this->label;
        $clone->is_default = false;
        $clone->delivery_cost = $this->delivery_cost;

        return $clone;
    }

    public function matchesDelivery(self $other): bool
    {
        if ($this->type !== $other->type) {
            return false;
        }

        return match ($this->type) {
            ShipmentAddressType::Courier => self::normalizeText($this->courier_address) === self::normalizeText($other->courier_address),
            ShipmentAddressType::NovaPoshtaBranch,
            ShipmentAddressType::NovaPoshtaPostomat => $this->np_city_ref === $other->np_city_ref
                && $this->np_warehouse_ref === $other->np_warehouse_ref,
        };
    }

    /** @return array<string, mixed> */
    public function toDeliveryArray(): array
    {
        return [
            'deliveryMethod' => $this->type->value,
            'courierAddress' => $this->courier_address,
            'npCityRef' => $this->np_city_ref,
            'npCityName' => $this->np_city_name,
            'npWarehouseRef' => $this->np_warehouse_ref,
            'npWarehouseName' => $this->np_warehouse_name,
            'deliveryCost' => $this->getDeliveryCost(),
        ];
    }

    public function getSummaryLabel(): string
    {
        return match ($this->type) {
            ShipmentAddressType::Courier => (string) ($this->courier_address ?? '—'),
            ShipmentAddressType::NovaPoshtaBranch,
            ShipmentAddressType::NovaPoshtaPostomat => trim(sprintf(
                '%s, %s',
                $this->np_city_name ?? '',
                $this->np_warehouse_name ?? '',
            ), ', ') ?: '—',
        };
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ShipmentAddressType
    {
        return $this->type;
    }

    public function setType(ShipmentAddressType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCourierAddress(): ?string
    {
        return $this->courier_address;
    }

    public function setCourierAddress(?string $courierAddress): static
    {
        $this->courier_address = self::nullableString($courierAddress);

        return $this;
    }

    public function getNpCityRef(): ?string
    {
        return $this->np_city_ref;
    }

    public function setNpCityRef(?string $npCityRef): static
    {
        $this->np_city_ref = self::nullableString($npCityRef);

        return $this;
    }

    public function getNpCityName(): ?string
    {
        return $this->np_city_name;
    }

    public function setNpCityName(?string $npCityName): static
    {
        $this->np_city_name = self::nullableString($npCityName);

        return $this;
    }

    public function getNpWarehouseRef(): ?string
    {
        return $this->np_warehouse_ref;
    }

    public function setNpWarehouseRef(?string $npWarehouseRef): static
    {
        $this->np_warehouse_ref = self::nullableString($npWarehouseRef);

        return $this;
    }

    public function getNpWarehouseName(): ?string
    {
        return $this->np_warehouse_name;
    }

    public function setNpWarehouseName(?string $npWarehouseName): static
    {
        $this->np_warehouse_name = self::nullableString($npWarehouseName);

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = self::nullableString($label);

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->is_default = $isDefault;

        return $this;
    }

    public function getDeliveryCost(): ?float
    {
        return $this->delivery_cost !== null ? (float) $this->delivery_cost : null;
    }

    public function setDeliveryCost(?float $deliveryCost): static
    {
        $this->delivery_cost = $deliveryCost !== null
            ? number_format(max(0, $deliveryCost), 2, '.', '')
            : null;

        return $this;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getOwnerUser(): ?User
    {
        return $this->user ?? $this->order?->getCustomer() ?? $this->cart?->getCustomer();
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function __toString(): string
    {
        if ($this->label !== null && $this->label !== '') {
            return $this->label;
        }

        return $this->getSummaryLabel();
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function normalizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
