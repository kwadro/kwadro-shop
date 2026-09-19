<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'shop_payment')]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(length: 32)]
    private string $method = '';

    #[ORM\Column(type: 'string', length: 32, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'UAH';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $gateway_reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $redirect_url = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $gateway_response = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $result_data = null;

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

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

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

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getGatewayReference(): ?string
    {
        return $this->gateway_reference;
    }

    public function setGatewayReference(?string $gatewayReference): static
    {
        $this->gateway_reference = $gatewayReference;

        return $this;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirect_url;
    }

    public function setRedirectUrl(?string $redirectUrl): static
    {
        $this->redirect_url = $redirectUrl;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getGatewayResponse(): ?array
    {
        return $this->gateway_response;
    }

    /** @param array<string, mixed>|null $gatewayResponse */
    public function setGatewayResponse(?array $gatewayResponse): static
    {
        $this->gateway_response = $gatewayResponse;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getResultData(): ?array
    {
        return $this->result_data;
    }

    /** @param array<string, mixed>|null $resultData */
    public function setResultData(?array $resultData): static
    {
        $this->result_data = $resultData;

        return $this;
    }

    public function getOrderLink(): string
    {
        return '';
    }

    public function __toString(): string
    {
        return sprintf('Payment #%d (%s)', $this->id ?? 0, $this->method);
    }
}
