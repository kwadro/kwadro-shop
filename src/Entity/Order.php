<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'shop_order')]
#[ORM\UniqueConstraint(name: 'uniq_shop_order_number', columns: ['order_number'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $order_number = '';

    #[ORM\Column(type: 'string', length: 32, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Created;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $customer = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $visitor_id = null;

    #[ORM\Column(length: 255)]
    private string $customer_name = '';

    #[ORM\Column(length: 32)]
    private string $customer_phone = '';

    #[ORM\Column(length: 255)]
    private string $customer_email = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $do_not_call = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $delivery_data = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $cart_data = [];

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $shipping_cost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $pay_amount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'UAH';

    #[ORM\Column(length: 5)]
    private string $locale = 'uk';

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $payments;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToOne(mappedBy: 'order', targetEntity: ShipmentAddress::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ShipmentAddress $shipmentAddress = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $np_waybill_ref = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $np_waybill_number = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $np_waybill_data = null;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->order_number;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->order_number = $orderNumber;

        return $this;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

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

    public function getVisitorId(): ?string
    {
        return $this->visitor_id;
    }

    public function setVisitorId(?string $visitorId): static
    {
        $this->visitor_id = $visitorId;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customer_name;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customer_name = trim($customerName);

        return $this;
    }

    public function getCustomerPhone(): string
    {
        return $this->customer_phone;
    }

    public function setCustomerPhone(string $customerPhone): static
    {
        $this->customer_phone = trim($customerPhone);

        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customer_email;
    }

    public function setCustomerEmail(string $customerEmail): static
    {
        $this->customer_email = trim($customerEmail);

        return $this;
    }

    public function isDoNotCall(): bool
    {
        return $this->do_not_call;
    }

    public function setDoNotCall(bool $doNotCall): static
    {
        $this->do_not_call = $doNotCall;

        return $this;
    }

    /** @param array<string, mixed> $contactData */
    public function applyContactData(array $contactData): static
    {
        $this->setCustomerName((string) ($contactData['customerName'] ?? ''));
        $this->setCustomerPhone((string) ($contactData['customerPhone'] ?? ''));
        $this->setCustomerEmail((string) ($contactData['customerEmail'] ?? ''));
        $this->setDoNotCall(filter_var($contactData['doNotCall'] ?? false, FILTER_VALIDATE_BOOL));

        return $this;
    }

    /** @return array<string, mixed> */
    public function getDeliveryData(): array
    {
        return $this->delivery_data;
    }

    /** @param array<string, mixed> $deliveryData */
    public function setDeliveryData(array $deliveryData): static
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
        if ($shipmentAddress !== null && $shipmentAddress->getOrder() !== $this) {
            $shipmentAddress->setOrder($this);
        }

        $this->shipmentAddress = $shipmentAddress;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getCartData(): array
    {
        return $this->cart_data;
    }

    /** @param array<string, mixed> $cartData */
    public function setCartData(array $cartData): static
    {
        $this->cart_data = $cartData;

        return $this;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = number_format($amount, 2, '.', '');

        return $this;
    }

    public function getShippingCost(): float
    {
        return (float) $this->shipping_cost;
    }

    public function getOrderTotal(): float
    {
        return round($this->getAmount(), 2);
    }

    public function getPayAmount(): float
    {
        return (float) $this->pay_amount;
    }

    public function setPayAmount(float $payAmount): static
    {
        $this->pay_amount = number_format(max(0, $payAmount), 2, '.', '');

        return $this;
    }

    public function addPayAmount(float $amount): static
    {
        if ($amount <= 0) {
            return $this;
        }

        return $this->setPayAmount(round($this->getPayAmount() + $amount, 2));
    }

    public function subtractPayAmount(float $amount): static
    {
        if ($amount <= 0) {
            return $this;
        }

        return $this->setPayAmount(round($this->getPayAmount() - $amount, 2));
    }

    public function getPaidAmount(): float
    {
        return $this->getPayAmount();
    }

    public function getAmountDue(): float
    {
        return max(0.0, round($this->getOrderTotal() - $this->getPayAmount(), 2));
    }

    public function setShippingCost(float $shippingCost): static
    {
        $this->shipping_cost = number_format(max(0, $shippingCost), 2, '.', '');

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setOrder($this);
        }

        return $this;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getOrder() === $this) {
            $item->setOrder(null);
        }

        return $this;
    }

    public function getShipmentAddressSummary(): string
    {
        return $this->shipmentAddress?->getSummaryLabel() ?? '—';
    }

    public function getContactSummary(): string
    {
        $parts = array_values(array_filter(
            [$this->customer_name, $this->customer_phone, $this->customer_email],
            static fn (string $part): bool => $part !== '',
        ));

        if ($this->do_not_call) {
            $parts[] = 'Не телефонувати';
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function getNpWaybillRef(): ?string
    {
        return $this->np_waybill_ref;
    }

    public function setNpWaybillRef(?string $npWaybillRef): static
    {
        $this->np_waybill_ref = $npWaybillRef !== null && trim($npWaybillRef) !== '' ? trim($npWaybillRef) : null;

        return $this;
    }

    public function getNpWaybillNumber(): ?string
    {
        return $this->np_waybill_number;
    }

    public function setNpWaybillNumber(?string $npWaybillNumber): static
    {
        $this->np_waybill_number = $npWaybillNumber !== null && trim($npWaybillNumber) !== '' ? trim($npWaybillNumber) : null;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getNpWaybillData(): ?array
    {
        return $this->np_waybill_data;
    }

    /** @param array<string, mixed>|null $npWaybillData */
    public function setNpWaybillData(?array $npWaybillData): static
    {
        $this->np_waybill_data = $npWaybillData;

        return $this;
    }

    public function hasNovaPoshtaWaybill(): bool
    {
        return $this->np_waybill_number !== null && $this->np_waybill_number !== '';
    }

    public function getPaymentsSummary(): string
    {
        return '';
    }

    public function getItemsSummaryLabel(): string
    {
        if ($this->items->isEmpty()) {
            if (!empty($this->cart_data['product']) && \is_array($this->cart_data['product'])) {
                $name = (string) ($this->cart_data['product']['name'] ?? '');
                $quantity = (int) ($this->cart_data['quantity'] ?? 0);

                return $name !== '' && $quantity > 0
                    ? sprintf('%s × %d', $name, $quantity)
                    : '—';
            }

            return '—';
        }

        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = sprintf('%s × %d', $item->getProductName(), $item->getQuantity());
        }

        return implode(', ', $parts);
    }

    public function __toString(): string
    {
        return $this->order_number !== '' ? $this->order_number : 'Order';
    }
}
