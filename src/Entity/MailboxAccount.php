<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\MailboxAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MailboxAccountRepository::class)]
#[ORM\Table(name: 'shop_mailbox_account')]
#[ORM\HasLifecycleCallbacks]
class MailboxAccount
{
    use TimeStampAbleTrait;

    public const DEFAULT_MESSAGE_GROUP = 'General';

    public const WILDCARD_FROM = '*';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Site $site = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private string $email = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $username = '';

    /** Encrypted password (libsodium). Never expose in forms. */
    #[ORM\Column(type: 'text')]
    private string $passwordEncrypted = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $imapHost = '';

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 65535)]
    private int $imapPort = 993;

    /** ssl|tls|none */
    #[ORM\Column(length: 16)]
    #[Assert\Choice(choices: ['ssl', 'tls', 'none'])]
    private string $imapEncryption = 'ssl';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $smtpHost = '';

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 65535)]
    private int $smtpPort = 465;

    /** ssl|tls|none */
    #[ORM\Column(length: 16)]
    #[Assert\Choice(choices: ['ssl', 'tls', 'none'])]
    private string $smtpEncryption = 'ssl';

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    /**
     * Allowlist of sender emails (From). Empty = accept all.
     * One entry per line (or comma/semicolon): email or email|Group name.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $allowedFromEmails = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    /** Highest IMAP UID already considered by sync (new mail is UID > this). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastSyncedRemoteUid = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastSyncError = null;

    /** Form-only password (not persisted). */
    private ?string $plainPassword = null;

    /** @var Collection<int, MailboxMessage> */
    #[ORM\OneToMany(targetEntity: MailboxMessage::class, mappedBy: 'mailbox', orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): ?Site
    {
        return $this->site;
    }

    public function setSite(?Site $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = trim($email);

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = trim($username);

        return $this;
    }

    public function getPasswordEncrypted(): string
    {
        return $this->passwordEncrypted;
    }

    public function setPasswordEncrypted(string $passwordEncrypted): static
    {
        $this->passwordEncrypted = $passwordEncrypted;

        return $this;
    }

    public function getImapHost(): string
    {
        return $this->imapHost;
    }

    public function setImapHost(string $imapHost): static
    {
        $this->imapHost = trim($imapHost);

        return $this;
    }

    public function getImapPort(): int
    {
        return $this->imapPort;
    }

    public function setImapPort(int $imapPort): static
    {
        $this->imapPort = $imapPort;

        return $this;
    }

    public function getImapEncryption(): string
    {
        return $this->imapEncryption;
    }

    public function setImapEncryption(string $imapEncryption): static
    {
        $this->imapEncryption = strtolower(trim($imapEncryption));

        return $this;
    }

    public function getSmtpHost(): string
    {
        return $this->smtpHost;
    }

    public function setSmtpHost(string $smtpHost): static
    {
        $this->smtpHost = trim($smtpHost);

        return $this;
    }

    public function getSmtpPort(): int
    {
        return $this->smtpPort;
    }

    public function setSmtpPort(int $smtpPort): static
    {
        $this->smtpPort = $smtpPort;

        return $this;
    }

    public function getSmtpEncryption(): string
    {
        return $this->smtpEncryption;
    }

    public function setSmtpEncryption(string $smtpEncryption): static
    {
        $this->smtpEncryption = strtolower(trim($smtpEncryption));

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getAllowedFromEmails(): ?string
    {
        return $this->allowedFromEmails;
    }

    public function setAllowedFromEmails(?string $allowedFromEmails): static
    {
        if ($allowedFromEmails === null) {
            $this->allowedFromEmails = null;

            return $this;
        }

        $normalized = trim($this->normalizeFilterRaw($allowedFromEmails));
        $this->allowedFromEmails = $normalized !== '' ? $normalized : null;

        return $this;
    }

    /**
     * @return array<string, string> Lowercased email (or *) => group name
     */
    public function getAllowedFromEmailGroups(): array
    {
        if ($this->allowedFromEmails === null || trim($this->allowedFromEmails) === '') {
            return [];
        }

        $raw = $this->normalizeFilterRaw($this->allowedFromEmails);

        $map = [];
        $lines = preg_split('/[\n,;]+/u', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $emailPart = $line;
            $group = self::DEFAULT_MESSAGE_GROUP;
            if (str_contains($line, '|')) {
                [$emailPart, $groupPart] = explode('|', $line, 2);
                $group = trim($groupPart);
                if ($group === '') {
                    $group = self::DEFAULT_MESSAGE_GROUP;
                }
            }

            $email = $this->normalizeFilterSender(trim($emailPart));
            if ($email === self::WILDCARD_FROM) {
                $map[self::WILDCARD_FROM] = $group;
                continue;
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $map[$email] = $group;
        }

        return $map;
    }

    private function normalizeFilterRaw(string $raw): string
    {
        // Keep line breaks if a WYSIWYG editor wrapped each rule in <p>/<div>/<br>.
        $raw = preg_replace('/<\s*br\s*\/?\s*>/iu', "\n", $raw) ?? $raw;
        $raw = preg_replace('/<\/\s*(p|div|li|tr|h[1-6])\s*>/iu', "\n", $raw) ?? $raw;
        $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $raw = preg_replace("/[ \t]+/u", ' ', $raw) ?? $raw;
        $raw = preg_replace("/\n{2,}/u", "\n", $raw) ?? $raw;

        return trim($raw);
    }

    private function normalizeFilterSender(string $sender): string
    {
        $sender = trim($sender);
        // ASCII *, fullwidth ＊, and bullet-like chars used as "all senders".
        if ($sender === '*' || $sender === '＊' || $sender === '✱' || $sender === '★') {
            return self::WILDCARD_FROM;
        }

        return strtolower($sender);
    }

    /**
     * Concrete sender emails only (excludes * wildcard).
     *
     * @return list<string>
     */
    public function getAllowedFromEmailList(): array
    {
        $emails = [];
        foreach (array_keys($this->getAllowedFromEmailGroups()) as $email) {
            if ($email !== self::WILDCARD_FROM) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    public function hasWildcardFromFilter(): bool
    {
        return isset($this->getAllowedFromEmailGroups()[self::WILDCARD_FROM]);
    }

    public function isFromAddressAllowed(string $fromAddress): bool
    {
        $allowed = $this->getAllowedFromEmailGroups();
        if ($allowed === [] || isset($allowed[self::WILDCARD_FROM])) {
            return true;
        }

        $from = strtolower(trim($fromAddress));
        if ($from === '') {
            return false;
        }

        return isset($allowed[$from]);
    }

    public function resolveMessageGroup(string $fromAddress): string
    {
        $allowed = $this->getAllowedFromEmailGroups();
        if ($allowed === []) {
            return self::DEFAULT_MESSAGE_GROUP;
        }

        $from = strtolower(trim($fromAddress));
        if ($from !== '' && isset($allowed[$from])) {
            return $allowed[$from];
        }

        return $allowed[self::WILDCARD_FROM] ?? self::DEFAULT_MESSAGE_GROUP;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): static
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }

    public function getLastSyncedRemoteUid(): ?string
    {
        return $this->lastSyncedRemoteUid;
    }

    public function setLastSyncedRemoteUid(?string $lastSyncedRemoteUid): static
    {
        $normalized = $lastSyncedRemoteUid !== null ? trim($lastSyncedRemoteUid) : null;
        $this->lastSyncedRemoteUid = $normalized !== '' ? $normalized : null;

        return $this;
    }

    public function getLastSyncError(): ?string
    {
        return $this->lastSyncError;
    }

    public function setLastSyncError(?string $lastSyncError): static
    {
        $this->lastSyncError = $lastSyncError;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /** @return Collection<int, MailboxMessage> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function __toString(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }

        return $this->email !== '' ? $this->email : 'Mailbox';
    }
}
