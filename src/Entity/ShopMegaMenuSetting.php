<?php

namespace App\Entity;

use App\Repository\ShopMegaMenuSettingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShopMegaMenuSettingRepository::class)]
#[ORM\Table(name: 'shop_mega_menu_setting')]
#[ORM\UniqueConstraint(name: 'uniq_shop_mega_menu_setting_site_locale', columns: ['site_id', 'locale_id'])]
#[UniqueEntity(fields: ['site', 'locale'])]
class ShopMegaMenuSetting
{
    public const MODE_AUTO = 'auto';
    public const MODE_MANUAL = 'manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Site $site = null;

    #[ORM\ManyToOne(targetEntity: Locale::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Locale $locale = null;

    #[ORM\Column(length: 20, options: ['default' => self::MODE_AUTO])]
    private string $mode = self::MODE_AUTO;

    /** @var Collection<int, ShopMegaMenuItem> */
    #[ORM\OneToMany(
        mappedBy: 'menuSetting',
        targetEntity: ShopMegaMenuItem::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

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

    public function getLocale(): ?Locale
    {
        return $this->locale;
    }

    public function setLocale(?Locale $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): static
    {
        $this->mode = $mode === self::MODE_MANUAL ? self::MODE_MANUAL : self::MODE_AUTO;

        return $this;
    }

    public function isManual(): bool
    {
        return $this->mode === self::MODE_MANUAL;
    }

    /** @return Collection<int, ShopMegaMenuItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ShopMegaMenuItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMenuSetting($this);
        }

        return $this;
    }

    public function removeItem(ShopMegaMenuItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getMenuSetting() === $this) {
            $item->setMenuSetting(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return trim(sprintf(
            'Mega menu: %s / %s',
            $this->site?->getDomain() ?? '',
            $this->locale?->getCode() ?? '',
        ));
    }
}
