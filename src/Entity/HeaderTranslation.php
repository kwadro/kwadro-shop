<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\HeaderTranslationRepository;
use App\Entity\HeaderSetting;
use App\Entity\Locale;

#[ORM\Entity(repositoryClass: HeaderTranslationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HeaderTranslation
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;
    #[ORM\ManyToOne(
            targetEntity: HeaderSetting::class,
            cascade: ['persist'],
            inversedBy: 'translations'
    )]
        private ?HeaderSetting $headersetting;
    #[ORM\ManyToOne(
            targetEntity: Locale::class,
            cascade: ['persist'],
            inversedBy: 'headertranslatelocales'
    )]
        private ?Locale $locale;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $title;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $menu_json;

    #[ORM\Column(type:"text", nullable:true)]
    private ?string $work_hours;

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

    public function getHeadersetting(): ?HeaderSetting
    {
        return $this->headersetting;
    }
    public function setHeadersetting(?HeaderSetting $headersetting): HeaderTranslation
    {
        $this->headersetting = $headersetting;
        return $this;
    }
    public function __toString(): string
    {
        return $this->headersetting;
    }

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }
    public function setLocale(?Locale $locale): HeaderTranslation
    {
        $this->locale = $locale;
        return $this;
    }
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setMenuJson(?string $menu_json): self
    {
        $this->menu_json = $menu_json;
        return $this;
    }

    public function getMenuJson(): ?string
    {
        return $this->menu_json;
    }

    public function setWorkHours(?string $work_hours): self
    {
        $this->work_hours = $work_hours;

        return $this;
    }

    public function getWorkHours(): ?string
    {
        return $this->work_hours;
    }

}
