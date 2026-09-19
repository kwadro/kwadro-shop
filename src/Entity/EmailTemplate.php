<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\EmailTemplateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmailTemplateRepository::class)]
#[ORM\Table(name: 'shop_email_template')]
#[ORM\UniqueConstraint(name: 'uniq_email_template_site_name', columns: ['site_id', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['site', 'name'], message: 'admin.email_template.name_unique')]
class EmailTemplate
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'emailTemplates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Site $site = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $subject = '';

    #[ORM\Column(type: 'string', length: 16, enumType: EmailTemplateType::class, options: ['default' => 'html'])]
    private EmailTemplateType $type = EmailTemplateType::Html;

    #[ORM\Column(type: 'string', length: 16, enumType: EmailTemplateContext::class, options: ['default' => 'order'])]
    private EmailTemplateContext $context = EmailTemplateContext::Order;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $additional_css = null;

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

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = trim($subject);

        return $this;
    }

    public function getType(): EmailTemplateType
    {
        return $this->type;
    }

    public function setType(EmailTemplateType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getContext(): EmailTemplateContext
    {
        return $this->context;
    }

    public function setContext(EmailTemplateContext $context): static
    {
        $this->context = $context;

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

    public function getAdditionalCss(): ?string
    {
        return $this->additional_css;
    }

    public function setAdditionalCss(?string $additionalCss): static
    {
        $this->additional_css = $additionalCss !== null && trim($additionalCss) !== ''
            ? $additionalCss
            : null;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Email template';
    }
}
