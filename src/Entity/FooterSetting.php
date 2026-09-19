<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\FooterSettingRepository;
use App\Entity\Site;
use App\Entity\FooterTranslation;

#[ORM\Entity(repositoryClass: FooterSettingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FooterSetting
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;
    #[ORM\ManyToOne(
            targetEntity: Site::class,
            cascade: ['persist'],
            inversedBy: 'footersettingsites'
    )]
        private ?Site $site;
    #[ORM\OneToMany(
        targetEntity: FooterTranslation::class,
        mappedBy: 'footersetting',
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
    public function setSite(?Site $site): FooterSetting
    {
        $this->site = $site;
        return $this;
    }
    public function __toString(): string
    {
        return $this->site;
    }

    public function addFooterTranslation(FooterTranslation $footertranslation): self
    {
        if(!$this->translations->contains($footertranslation)) {
           $this->translations[] = $footertranslation;
           $footertranslation->setFootersetting($this);
        }
        return $this;
    }

    public function removeFooterTranslation(FooterTranslation $footertranslation): self
    {
        if($this->translations->removeElement($footertranslation)) {
           if ($footertranslation->getFootersetting() === $this) {
               $footertranslation->setFootersetting(null);
           }
        }
        return $this;
    }

    public function getTranslations(): ?Collection
    {
        return $this->translations;
    }

}
