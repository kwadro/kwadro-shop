<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\EmailLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailLogRepository::class)]
#[ORM\Table(name: 'shop_email_log')]
#[ORM\HasLifecycleCallbacks]
class EmailLog
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $event_code = '';

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: OrderEmail::class)]
    #[ORM\JoinColumn(name: 'order_email_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?OrderEmail $orderEmail = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $order_number = null;

    #[ORM\Column(length: 255)]
    private string $recipient_email = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sender_email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sender_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $is_html = false;

    #[ORM\Column(type: 'string', length: 16, enumType: EmailLogStatus::class)]
    private EmailLogStatus $status = EmailLogStatus::Skipped;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error_message = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $skip_reason = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $context = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventCode(): string
    {
        return $this->event_code;
    }

    public function setEventCode(string $eventCode): static
    {
        $this->event_code = trim($eventCode);

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

    public function getOrderEmail(): ?OrderEmail
    {
        return $this->orderEmail;
    }

    public function setOrderEmail(?OrderEmail $orderEmail): static
    {
        $this->orderEmail = $orderEmail;

        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->order_number;
    }

    public function setOrderNumber(?string $orderNumber): static
    {
        $this->order_number = $orderNumber !== null && trim($orderNumber) !== '' ? trim($orderNumber) : null;

        return $this;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipient_email;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipient_email = trim($recipientEmail);

        return $this;
    }

    public function getSenderEmail(): ?string
    {
        return $this->sender_email;
    }

    public function setSenderEmail(?string $senderEmail): static
    {
        $this->sender_email = $senderEmail !== null && trim($senderEmail) !== '' ? trim($senderEmail) : null;

        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->sender_name;
    }

    public function setSenderName(?string $senderName): static
    {
        $this->sender_name = $senderName !== null && trim($senderName) !== '' ? trim($senderName) : null;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function isHtml(): bool
    {
        return $this->is_html;
    }

    public function setIsHtml(bool $isHtml): static
    {
        $this->is_html = $isHtml;

        return $this;
    }

    public function getStatus(): EmailLogStatus
    {
        return $this->status;
    }

    public function setStatus(EmailLogStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->error_message = $errorMessage;

        return $this;
    }

    public function getSkipReason(): ?string
    {
        return $this->skip_reason;
    }

    public function setSkipReason(?string $skipReason): static
    {
        $this->skip_reason = $skipReason;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }

    /** @param array<string, mixed>|null $context */
    public function setContext(?array $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function __toString(): string
    {
        if ($this->subject !== null && $this->subject !== '') {
            return $this->subject;
        }

        return $this->event_code !== '' ? $this->event_code : 'Email log';
    }
}
