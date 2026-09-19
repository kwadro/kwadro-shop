<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\SeoSettingRepository;
use App\Entity\Site;
use App\Entity\SeoSettingsTranslation;

#[ORM\Entity(repositoryClass: SeoSettingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SeoSetting
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;
    #[ORM\ManyToOne(
            targetEntity: Site::class,
            cascade: ['persist'],
            inversedBy: 'seosettingsites'
    )]
        private ?Site $site;
    #[ORM\OneToMany(
        targetEntity: SeoSettingsTranslation::class,
        mappedBy: 'seosetting',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
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

    public function getSite(): ?Site
    {
        return $this->site;
    }
    public function setSite(?Site $site): SeoSetting
    {
        $this->site = $site;
        return $this;
    }
    public function __toString(): string
    {
        return $this->site;
    }

    public function addSeoSettingsTranslation(SeoSettingsTranslation $seosettingstranslation): self
    {
        if(!$this->translations->contains($seosettingstranslation)) {
           $this->translations[] = $seosettingstranslation;
           $seosettingstranslation->setSeosetting($this);
        }
        return $this;
    }

    public function removeSeoSettingsTranslation(SeoSettingsTranslation $seosettingstranslation): self
    {
        if($this->translations->removeElement($seosettingstranslation)) {
           if ($seosettingstranslation->getSeosetting() === $this) {
               $seosettingstranslation->setSeosetting(null);
           }
        }
        return $this;
    }

    public function getTranslations(): ?Collection
    {
        return $this->translations;
    }

}
