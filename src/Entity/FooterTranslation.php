<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\FooterTranslationRepository;
use App\Entity\FooterSetting;
use App\Entity\Locale;

#[ORM\Entity(repositoryClass: FooterTranslationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FooterTranslation
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;
    #[ORM\ManyToOne(
            targetEntity: FooterSetting::class,
            cascade: ['persist'],
            inversedBy: 'translations'
    )]
        private ?FooterSetting $footersetting;
    #[ORM\ManyToOne(
            targetEntity: Locale::class,
            cascade: ['persist'],
            inversedBy: 'footertranslatelocales'
    )]
        private ?Locale $locale;

    #[ORM\Column(type:"text", nullable:true)]
    private ?string $content;

    public function __construct()
    {
    }
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFootersetting(): ?FooterSetting
    {
        return $this->footersetting;
    }
    public function setFootersetting(?FooterSetting $footersetting): FooterTranslation
    {
        $this->footersetting = $footersetting;
        return $this;
    }
    public function __toString(): string
    {
        return $this->footersetting;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }
    public function setLocale(?Locale $locale): FooterTranslation
    {
        $this->locale = $locale;
        return $this;
    }
    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

}
