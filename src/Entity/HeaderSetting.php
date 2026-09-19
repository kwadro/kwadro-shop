<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\HeaderSettingRepository;
use App\Entity\Site;
use App\Entity\HeaderTranslation;

#[ORM\Entity(repositoryClass: HeaderSettingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HeaderSetting
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;
    #[ORM\ManyToOne(
            targetEntity: Site::class,
            cascade: ['persist'],
            inversedBy: 'headersettingsites'
    )]
        private ?Site $site;
    #[ORM\OneToMany(
        targetEntity: HeaderTranslation::class,
        mappedBy: 'headersetting',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $translations;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $logo;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $favicon;

    #[ORM\Column(type:"text", nullable:true)]
    private ?string $phones;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '100.00'])]
    private ?string $courier_delivery_cost = '100.00';

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
    public function setSite(?Site $site): HeaderSetting
    {
        $this->site = $site;
        return $this;
    }
    public function __toString(): string
    {
        return $this->site;
    }

    public function addHeaderTranslation(HeaderTranslation $headertranslation): self
    {
        if(!$this->translations->contains($headertranslation)) {
           $this->translations[] = $headertranslation;
           $headertranslation->setHeadersetting($this);
        }
        return $this;
    }

    public function removeHeaderTranslation(HeaderTranslation $headertranslation): self
    {
        if($this->translations->removeElement($headertranslation)) {
           if ($headertranslation->getHeadersetting() === $this) {
               $headertranslation->setHeadersetting(null);
           }
        }
        return $this;
    }

    public function getTranslations(): ?Collection
    {
        return $this->translations;
    }
    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }
    public function setFavicon(?string $favicon): self
    {
        $this->favicon = $favicon;
        return $this;
    }

    public function getFavicon(): ?string
    {
        return $this->favicon;
    }

    public function setPhones(?string $phones): self
    {
        $this->phones = $phones;

        return $this;
    }

    public function getPhones(): ?string
    {
        return $this->phones;
    }

    public function getCourierDeliveryCost(): ?float
    {
        return $this->courier_delivery_cost !== null ? (float) $this->courier_delivery_cost : null;
    }

    public function setCourierDeliveryCost(?float $courierDeliveryCost): self
    {
        $this->courier_delivery_cost = $courierDeliveryCost !== null
            ? number_format(max(0, $courierDeliveryCost), 2, '.', '')
            : null;

        return $this;
    }

}
