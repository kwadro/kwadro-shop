<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\UserGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
#[ORM\Table(name: 'app_user_group')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_group_code', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class UserGroup
{
    use TimeStampAbleTrait;

    public const CODE_SUPER_ADMINISTRATOR = 'super_administrator';
    public const CODE_ADMINISTRATOR = 'administrator';
    public const CODE_CLIENT = 'client';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /** Stable machine code used in access checks. */
    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z][a-z0-9_]*$/', message: 'Group code must be lowercase snake_case.')]
    private string $code = '';

    /** Catalog price discount for logged-in members of this group (0–100). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '0.00'])]
    #[Assert\Range(min: 0, max: 100)]
    private string $discountPercent = '0.00';

    #[ORM\ManyToMany(targetEntity: UserRole::class)]
    #[ORM\JoinTable(name: 'app_user_group_role')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtolower(trim($code));

        return $this;
    }

    public function getDiscountPercent(): float
    {
        return max(0.0, min(100.0, (float) $this->discountPercent));
    }

    public function setDiscountPercent(float|int|string|null $discountPercent): static
    {
        $value = round((float) ($discountPercent ?? 0), 2);
        if ($value < 0) {
            $value = 0.0;
        }
        if ($value > 100) {
            $value = 100.0;
        }
        $this->discountPercent = number_format($value, 2, '.', '');

        return $this;
    }

    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(UserRole $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }

        return $this;
    }

    public function removeRole(UserRole $role): static
    {
        $this->roles->removeElement($role);

        return $this;
    }

    public function hasRoleCode(string $roleCode): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getCode() === $roleCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getRoleCodes(): array
    {
        $codes = [];
        foreach ($this->roles as $role) {
            $code = $role->getCode();
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : $this->code;
    }
}
