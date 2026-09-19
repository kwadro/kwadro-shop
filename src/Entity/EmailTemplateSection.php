<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\EmailTemplateSectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailTemplateSectionRepository::class)]
#[ORM\Table(name: 'shop_email_template_section')]
#[ORM\UniqueConstraint(name: 'uniq_email_template_section_site_name', columns: ['site_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['site', 'name'], message: 'admin.email_template_section.name_unique')]
class EmailTemplateSection
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'emailTemplateSections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Site $site = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[a-z0-9_-]+$/', message: 'admin.email_template_section.name_format')]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $content = '';

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
        $this->name = strtolower(trim($name));

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Email template section';
    }
}
