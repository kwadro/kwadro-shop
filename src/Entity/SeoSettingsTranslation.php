<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\SeoSettingsTranslationRepository;
use App\Entity\SeoSetting;
use App\Entity\Locale;

#[ORM\Entity(repositoryClass: SeoSettingsTranslationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SeoSettingsTranslation
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;
    #[ORM\ManyToOne(
            targetEntity: SeoSetting::class,
            cascade: ['persist'],
            inversedBy: 'translations'
    )]
        private ?SeoSetting $seosetting;
    #[ORM\ManyToOne(
            targetEntity: Locale::class,
            cascade: ['persist'],
            inversedBy: 'seosettingtranslatelocales'
    )]
        private ?Locale $locale;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $meta_title;

    #[ORM\Column(type:"text", nullable:true)]
    private ?string $meta_description;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $meta_keywords;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $author;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $og_title;

    #[ORM\Column(type:"text", nullable:true)]
    private ?string $og_description;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $og_type;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $og_image;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $gtm_code;

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

    public function getSeosetting(): ?SeoSetting
    {
        return $this->seosetting;
    }
    public function setSeosetting(?SeoSetting $seosetting): SeoSettingsTranslation
    {
        $this->seosetting = $seosetting;
        return $this;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }
    public function setLocale(?Locale $locale): SeoSettingsTranslation
    {
        $this->locale = $locale;
        return $this;
    }
    public function setMetaTitle(?string $meta_title): self
    {
        $this->meta_title = $meta_title;
        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->meta_title;
    }
    public function __toString(): string
    {
        return $this->meta_title;
    }
    public function setMetaDescription(?string $meta_description): self
    {
        $this->meta_description = $meta_description;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->meta_description;
    }
    public function setMetaKeywords(?string $meta_keywords): self
    {
        $this->meta_keywords = $meta_keywords;
        return $this;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->meta_keywords;
    }
    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }
    public function setOgTitle(?string $og_title): self
    {
        $this->og_title = $og_title;
        return $this;
    }

    public function getOgTitle(): ?string
    {
        return $this->og_title;
    }
    public function setOgDescription(?string $og_description): self
    {
        $this->og_description = $og_description;
        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->og_description;
    }
    public function setOgType(?string $og_type): self
    {
        $this->og_type = $og_type;
        return $this;
    }

    public function getOgType(): ?string
    {
        return $this->og_type;
    }
    public function setOgImage(?string $og_image): self
    {
        $this->og_image = $og_image;
        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->og_image;
    }
    public function setGtmCode(?string $gtm_code): self
    {
        $this->gtm_code = $gtm_code;
        return $this;
    }

    public function getGtmCode(): ?string
    {
        return $this->gtm_code;
    }

}
