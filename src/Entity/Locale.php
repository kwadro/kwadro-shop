<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\LocaleRepository;
use App\Entity\HeaderTranslation;
use App\Entity\FooterTranslation;
use App\Entity\SeoSettingsTranslation;
use App\Entity\MegaMenuTranslation;
use App\Entity\Popularsearch;

#[ORM\Entity(repositoryClass: LocaleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Locale
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $code;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $name;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $is_default;
    #[ORM\OneToMany(
        targetEntity: HeaderTranslation::class,
        mappedBy: 'locale',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $headertranslatelocales;
    #[ORM\OneToMany(
        targetEntity: FooterTranslation::class,
        mappedBy: 'locale',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $footertranslatelocales;
    #[ORM\OneToMany(
        targetEntity: SeoSettingsTranslation::class,
        mappedBy: 'locale',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $seosettingtranslatelocales;
    #[ORM\OneToMany(
        targetEntity: MegaMenuTranslation::class,
        mappedBy: 'locale',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $megamenutranslatelocales;
    #[ORM\OneToMany(
        targetEntity: Popularsearch::class,
        mappedBy: 'locale',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $popularsearchlocales;

    public function __construct()
    {
        $this->headertranslatelocales = new ArrayCollection();
        $this->footertranslatelocales = new ArrayCollection();
        $this->seosettingtranslatelocales = new ArrayCollection();
        $this->megamenutranslatelocales = new ArrayCollection();
        $this->popularsearchlocales = new ArrayCollection();
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
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
    public function __toString(): string
    {
        return $this->name;
    }
    public function setIsDefault(?string $is_default): self
    {
        $this->is_default = $is_default;
        return $this;
    }

    public function getIsDefault(): ?string
    {
        return $this->is_default;
    }

    public function addHeaderTranslation(HeaderTranslation $headertranslation): self
    {
        if(!$this->headertranslatelocales->contains($headertranslation)) {
           $this->headertranslatelocales[] = $headertranslation;
           $headertranslation->setLocale($this);
        }
        return $this;
    }

    public function removeHeaderTranslation(HeaderTranslation $headertranslation): self
    {
        if($this->headertranslatelocales->removeElement($headertranslation)) {
           if ($headertranslation->getLocale() === $this) {
               $headertranslation->setLocale(null);
           }
        }
        return $this;
    }

    public function getHeadertranslatelocales(): ?Collection
    {
        return $this->headertranslatelocales;
    }

    public function addFooterTranslation(FooterTranslation $footertranslation): self
    {
        if(!$this->footertranslatelocales->contains($footertranslation)) {
           $this->footertranslatelocales[] = $footertranslation;
           $footertranslation->setLocale($this);
        }
        return $this;
    }

    public function removeFooterTranslation(FooterTranslation $footertranslation): self
    {
        if($this->footertranslatelocales->removeElement($footertranslation)) {
           if ($footertranslation->getLocale() === $this) {
               $footertranslation->setLocale(null);
           }
        }
        return $this;
    }

    public function getFootertranslatelocales(): ?Collection
    {
        return $this->footertranslatelocales;
    }

    public function addSeoSettingsTranslation(SeoSettingsTranslation $seosettingstranslation): self
    {
        if(!$this->seosettingtranslatelocales->contains($seosettingstranslation)) {
           $this->seosettingtranslatelocales[] = $seosettingstranslation;
           $seosettingstranslation->setLocale($this);
        }
        return $this;
    }

    public function removeSeoSettingsTranslation(SeoSettingsTranslation $seosettingstranslation): self
    {
        if($this->seosettingtranslatelocales->removeElement($seosettingstranslation)) {
           if ($seosettingstranslation->getLocale() === $this) {
               $seosettingstranslation->setLocale(null);
           }
        }
        return $this;
    }

    public function getSeosettingtranslatelocales(): ?Collection
    {
        return $this->seosettingtranslatelocales;
    }

    public function addMegaMenuTranslation(MegaMenuTranslation $megamenutranslation): self
    {
        if(!$this->megamenutranslatelocales->contains($megamenutranslation)) {
           $this->megamenutranslatelocales[] = $megamenutranslation;
           $megamenutranslation->setLocale($this);
        }
        return $this;
    }

    public function removeMegaMenuTranslation(MegaMenuTranslation $megamenutranslation): self
    {
        if($this->megamenutranslatelocales->removeElement($megamenutranslation)) {
           if ($megamenutranslation->getLocale() === $this) {
               $megamenutranslation->setLocale(null);
           }
        }
        return $this;
    }

    public function getMegamenutranslatelocales(): ?Collection
    {
        return $this->megamenutranslatelocales;
    }

    public function addPopularsearch(Popularsearch $popularsearch): self
    {
        if(!$this->popularsearchlocales->contains($popularsearch)) {
           $this->popularsearchlocales[] = $popularsearch;
           $popularsearch->setLocale($this);
        }
        return $this;
    }

    public function removePopularsearch(Popularsearch $popularsearch): self
    {
        if($this->popularsearchlocales->removeElement($popularsearch)) {
           if ($popularsearch->getLocale() === $this) {
               $popularsearch->setLocale(null);
           }
        }
        return $this;
    }

    public function getPopularsearchlocales(): ?Collection
    {
        return $this->popularsearchlocales;
    }

}
