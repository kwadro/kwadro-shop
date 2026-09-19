<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimeStampAbleTrait;

    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $full_name = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    private ?string $plainPassword = null;

    /** @var Collection<int, ShipmentAddress> */
    #[ORM\OneToMany(targetEntity: ShipmentAddress::class, mappedBy: 'user', cascade: ['persist'], orphanRemoval: true)]
    private Collection $shipmentAddresses;

    public function __construct()
    {
        $this->shipmentAddresses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function setId(?int $id): static
    {
        $this->id = $id;
        return $this;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    public function setFullName(?string $fullName): static
    {
        $this->full_name = self::nullableString($fullName);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = self::nullableString($phone);

        return $this;
    }

    /** @param array<string, mixed> $contactData */
    public function applyContactData(array $contactData): static
    {
        $name = self::nullableString($contactData['customerName'] ?? null);
        $phone = self::nullableString($contactData['customerPhone'] ?? null);

        if ($name !== null) {
            $this->full_name = $name;
        }

        if ($phone !== null) {
            $this->phone = $phone;
        }

        return $this;
    }

    /** @return array<string, string> */
    public function toContactDataArray(): array
    {
        return [
            'customerName' => (string) ($this->full_name ?? ''),
            'customerPhone' => (string) ($this->phone ?? ''),
            'customerEmail' => (string) ($this->email ?? ''),
        ];
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

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

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    /** @return Collection<int, ShipmentAddress> */
    public function getShipmentAddresses(): Collection
    {
        return $this->shipmentAddresses;
    }

    /** @return list<ShipmentAddress> */
    public function getSavedShipmentAddresses(): array
    {
        $addresses = [];
        foreach ($this->shipmentAddresses as $address) {
            if ($address->getCart() !== null || $address->getOrder() !== null) {
                continue;
            }

            $addresses[] = $address;
        }

        return $addresses;
    }

    public function addShipmentAddress(ShipmentAddress $shipmentAddress): static
    {
        if (!$this->shipmentAddresses->contains($shipmentAddress)) {
            $this->shipmentAddresses->add($shipmentAddress);
            $shipmentAddress->setUser($this);
        }

        return $this;
    }

    public function removeShipmentAddress(ShipmentAddress $shipmentAddress): static
    {
        if ($this->shipmentAddresses->removeElement($shipmentAddress) && $shipmentAddress->getUser() === $this) {
            $shipmentAddress->setUser(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->email;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
