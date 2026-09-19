<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\BankAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BankAccountRepository::class)]
#[ORM\Table(name: 'shop_bank_account')]
#[ORM\HasLifecycleCallbacks]
class BankAccount
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Site $site = null;

    #[ORM\ManyToOne(targetEntity: Locale::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Locale $locale = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(length: 34)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 34)]
    private string $iban = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $bank_name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $recipient = '';

    #[ORM\Column(length: 35)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 35)]
    private string $edrpou = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $is_default = false;

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

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(?Locale $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getIban(): string
    {
        return $this->iban;
    }

    public function setIban(string $iban): static
    {
        $this->iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');

        return $this;
    }

    public function getBankName(): string
    {
        return $this->bank_name;
    }

    public function setBankName(string $bankName): static
    {
        $this->bank_name = trim($bankName);

        return $this;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): static
    {
        $this->recipient = trim($recipient);

        return $this;
    }

    public function getEdrpou(): string
    {
        return $this->edrpou;
    }

    public function setEdrpou(string $edrpou): static
    {
        $this->edrpou = trim($edrpou);

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

    /** @return array<string, string> */
    public function toEmailParameterMap(): array
    {
        return [
            'shop_iban' => $this->iban,
            'shop_bank_name' => $this->bank_name,
            'shop_bank_recipient' => $this->recipient,
            'shop_edrpou' => $this->edrpou,
        ];
    }

    public function __toString(): string
    {
        if ($this->title !== '') {
            return $this->title;
        }

        if ($this->recipient !== '') {
            return $this->recipient;
        }

        return $this->iban !== '' ? $this->iban : 'Bank account';
    }
}
