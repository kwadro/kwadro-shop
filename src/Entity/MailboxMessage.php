<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\MailboxMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MailboxMessageRepository::class)]
#[ORM\Table(name: 'shop_mailbox_message')]
#[ORM\UniqueConstraint(name: 'uniq_mailbox_remote_uid', columns: ['mailbox_id', 'remote_uid'])]
#[ORM\Index(name: 'idx_mailbox_message_received', columns: ['received_at'])]
#[ORM\Index(name: 'idx_mailbox_message_notified', columns: ['is_notified', 'is_seen'])]
#[ORM\HasLifecycleCallbacks]
class MailboxMessage
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MailboxAccount::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?MailboxAccount $mailbox = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $remoteUid = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(length: 255)]
    private string $fromAddress = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $toAddresses = null;

    #[ORM\Column(length: 500)]
    private string $subject = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyPreview = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyHtml = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSeen = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isNotified = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasAttachments = false;

    #[ORM\Column(length: 64, options: ['default' => 'INBOX'])]
    private string $folder = 'INBOX';

    public function __construct()
    {
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMailbox(): ?MailboxAccount
    {
        return $this->mailbox;
    }

    public function setMailbox(?MailboxAccount $mailbox): static
    {
        $this->mailbox = $mailbox;

        return $this;
    }

    public function getRemoteUid(): string
    {
        return $this->remoteUid;
    }

    public function setRemoteUid(string $remoteUid): static
    {
        $this->remoteUid = trim($remoteUid);

        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): static
    {
        $this->messageId = $messageId !== null ? trim($messageId) : null;

        return $this;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): static
    {
        $this->fromAddress = trim($fromAddress);

        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): static
    {
        $normalized = $fromName !== null ? trim($fromName) : null;
        $this->fromName = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getToAddresses(): ?string
    {
        return $this->toAddresses;
    }

    public function setToAddresses(?string $toAddresses): static
    {
        $this->toAddresses = $toAddresses;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = mb_substr(trim($subject), 0, 500);

        return $this;
    }

    public function getBodyPreview(): ?string
    {
        return $this->bodyPreview;
    }

    public function setBodyPreview(?string $bodyPreview): static
    {
        $this->bodyPreview = $bodyPreview !== null
            ? mb_substr(trim($bodyPreview), 0, 500)
            : null;

        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): static
    {
        $this->bodyText = $bodyText;

        return $this;
    }

    public function getBodyHtml(): ?string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(?string $bodyHtml): static
    {
        $this->bodyHtml = $bodyHtml;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function isSeen(): bool
    {
        return $this->isSeen;
    }

    public function setIsSeen(bool $isSeen): static
    {
        $this->isSeen = $isSeen;

        return $this;
    }

    public function isNotified(): bool
    {
        return $this->isNotified;
    }

    public function setIsNotified(bool $isNotified): static
    {
        $this->isNotified = $isNotified;

        return $this;
    }

    public function hasAttachments(): bool
    {
        return $this->hasAttachments;
    }

    public function setHasAttachments(bool $hasAttachments): static
    {
        $this->hasAttachments = $hasAttachments;

        return $this;
    }

    public function getFolder(): string
    {
        return $this->folder;
    }

    public function setFolder(string $folder): static
    {
        $this->folder = trim($folder) !== '' ? trim($folder) : 'INBOX';

        return $this;
    }

    public function getFromDisplay(): string
    {
        if ($this->fromName !== null && $this->fromName !== '') {
            return sprintf('%s <%s>', $this->fromName, $this->fromAddress);
        }

        return $this->fromAddress;
    }

    public function __toString(): string
    {
        return $this->subject !== '' ? $this->subject : sprintf('Message #%s', $this->id ?? '?');
    }
}
