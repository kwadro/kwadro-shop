<?php

namespace App\Entity;

use DateTimeImmutable;
use App\Entity\Traits\TimeStampAbleTrait;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\MegaMenuTypeRepository;
use App\Entity\MegaMenuTranslation;

#[ORM\Entity(repositoryClass: MegaMenuTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MegaMenuType
{
    use TimestampableTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]

    #[ORM\Column(type:"integer", nullable:true)]
    private ?int $id;

    #[ORM\Column(type:"string", nullable:true)]
    private ?string $name;
    #[ORM\OneToMany(
        targetEntity: MegaMenuTranslation::class,
        mappedBy: 'megamenutype',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
        public ?Collection $megamenutranslations;

    public function __construct()
    {
        $this->megamenutranslations = new ArrayCollection();
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

    public function addMegaMenuTranslation(MegaMenuTranslation $megamenutranslation): self
    {
        if(!$this->megamenutranslations->contains($megamenutranslation)) {
           $this->megamenutranslations[] = $megamenutranslation;
           $megamenutranslation->setMegamenutype($this);
        }
        return $this;
    }

    public function removeMegaMenuTranslation(MegaMenuTranslation $megamenutranslation): self
    {
        if($this->megamenutranslations->removeElement($megamenutranslation)) {
           if ($megamenutranslation->getMegamenutype() === $this) {
               $megamenutranslation->setMegamenutype(null);
           }
        }
        return $this;
    }

    public function getMegamenutranslations(): ?Collection
    {
        return $this->megamenutranslations;
    }

}
