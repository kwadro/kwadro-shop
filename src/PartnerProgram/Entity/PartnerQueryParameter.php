<?php

namespace App\PartnerProgram\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\PartnerProgram\Repository\PartnerQueryParameterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PartnerQueryParameterRepository::class)]
#[ORM\Table(name: 'shop_partner_query_parameter')]
#[ORM\UniqueConstraint(name: 'uniq_shop_partner_query_parameter_name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'admin.partner_query_parameter.name_unique')]
class PartnerQueryParameter
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Query parameter name that may carry a partner referral key. */
    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 64)]
    #[Assert\Regex(pattern: '/^[A-Za-z][A-Za-z0-9_-]*$/', message: 'admin.partner_query_parameter.name_format')]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $normalized = $note !== null ? trim($note) : null;
        $this->note = $normalized !== '' ? $normalized : null;

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

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Partner query parameter';
    }
}
