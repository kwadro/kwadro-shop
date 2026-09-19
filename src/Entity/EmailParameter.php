<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\EmailParameterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailParameterRepository::class)]
#[ORM\Table(name: 'shop_email_parameter')]
#[ORM\UniqueConstraint(name: 'uniq_email_parameter_site_locale_name', columns: ['site_id', 'locale_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['site', 'locale', 'name'], message: 'admin.email_parameter.name_unique')]
class EmailParameter
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

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: 'admin.email_parameter.name_format')]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 16, enumType: EmailParameterType::class, options: ['default' => 'text'])]
    private EmailParameterType $type = EmailParameterType::Text;

    #[ORM\Column(type: 'string', length: 32, enumType: EmailParameterSection::class, options: ['default' => 'general'])]
    private EmailParameterSection $section = EmailParameterSection::General;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $value = '';

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = strtolower(trim($name));
        if ($this->name === 'shop_logo_url') {
            $this->type = EmailParameterType::Image;
        }

        return $this;
    }

    public function getSection(): EmailParameterSection
    {
        return $this->section;
    }

    public function setSection(EmailParameterSection $section): static
    {
        $this->section = $section;

        return $this;
    }

    public function getType(): EmailParameterType
    {
        return $this->type;
    }

    public function setType(EmailParameterType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function __toString(): string
    {
        if ($this->name === '') {
            return 'Email parameter';
        }

        $localeCode = $this->locale?->getCode() ?? '';

        return $localeCode !== ''
            ? sprintf('%s (%s)', $this->name, $localeCode)
            : $this->name;
    }
}
