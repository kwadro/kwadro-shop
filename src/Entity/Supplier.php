<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'shop_supplier')]
#[ORM\UniqueConstraint(name: 'uniq_shop_supplier_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Supplier
{
    use TimeStampAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    /** @var Collection<int, ProductOffer> */
    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: ProductOffer::class, cascade: ['persist'], orphanRemoval: false)]
    private Collection $offers;

    public function __construct()
    {
        $this->offers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

        return $this;
    }

    public function ensureSlug(): static
    {
        if ($this->slug !== '') {
            return $this;
        }

        $base = $this->slugify($this->name !== '' ? $this->name : 'supplier');
        $this->slug = $base !== '' ? $base : 'supplier';

        return $this;
    }

    private function slugify(string $value): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd', 'е' => 'e', 'є' => 'ye',
            'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ю' => 'yu',
            'я' => 'ya', 'ы' => 'y', 'э' => 'e', 'ъ' => '',
        ];

        $value = mb_strtolower(trim($value));
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';

        return trim($value, '-_');
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description !== null ? trim($description) : null;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone !== null ? trim($phone) : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email !== null ? trim($email) : null;

        return $this;
    }

    /** @return Collection<int, ProductOffer> */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(ProductOffer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setSupplier($this);
        }

        return $this;
    }

    public function removeOffer(ProductOffer $offer): static
    {
        if ($this->offers->removeElement($offer) && $offer->getSupplier() === $this) {
            $offer->setSupplier(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : 'Supplier';
    }
}
