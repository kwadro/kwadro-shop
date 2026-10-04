<?php

namespace App\Entity;

use App\Entity\Traits\TimeStampAbleTrait;
use App\Repository\BlogArticleRepository;
use App\Routing\ShopRoutes;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogArticleRepository::class)]
#[ORM\Table(name: 'shop_blog_article')]
#[ORM\UniqueConstraint(name: 'uniq_blog_article_site_locale_slug', columns: ['site_id', 'locale_id', 'slug'])]
#[ORM\Index(name: 'idx_blog_article_published', columns: ['enabled', 'published_at'])]
#[ORM\HasLifecycleCallbacks]
class BlogArticle
{
    use TimeStampAbleTrait;

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

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $meta_title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $meta_description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $og_title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $og_description = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $og_type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $og_image = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '#^(?:'.ShopRoutes::SLUG_REQUIREMENTS.')$#',
        message: 'Slug may contain only letters, digits, hyphen and underscore.',
    )]
    private string $slug = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $content = '';

    /** Plain-text Facebook post draft (no HTML). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $facebookDraft = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** @var Collection<int, BlogCategory> */
    #[ORM\ManyToMany(targetEntity: BlogCategory::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'shop_blog_article_category')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->publishedAt = new \DateTimeImmutable();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->meta_title;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $metaTitle = $metaTitle !== null ? trim($metaTitle) : null;
        $this->meta_title = $metaTitle !== '' ? $metaTitle : null;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->meta_description;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $metaDescription = $metaDescription !== null ? trim($metaDescription) : null;
        $this->meta_description = $metaDescription !== '' ? $metaDescription : null;

        return $this;
    }

    public function getOgTitle(): ?string
    {
        return $this->og_title;
    }

    public function setOgTitle(?string $ogTitle): static
    {
        $ogTitle = $ogTitle !== null ? trim($ogTitle) : null;
        $this->og_title = $ogTitle !== '' ? $ogTitle : null;

        return $this;
    }

    public function getOgDescription(): ?string
    {
        return $this->og_description;
    }

    public function setOgDescription(?string $ogDescription): static
    {
        $ogDescription = $ogDescription !== null ? trim($ogDescription) : null;
        $this->og_description = $ogDescription !== '' ? $ogDescription : null;

        return $this;
    }

    public function getOgType(): ?string
    {
        return $this->og_type;
    }

    public function setOgType(?string $ogType): static
    {
        $ogType = $ogType !== null ? trim($ogType) : null;
        $this->og_type = $ogType !== '' ? $ogType : null;

        return $this;
    }

    public function getOgImage(): ?string
    {
        return $this->og_image;
    }

    public function setOgImage(?string $ogImage): static
    {
        $ogImage = $ogImage !== null ? trim($ogImage) : null;
        $this->og_image = $ogImage !== '' ? $ogImage : null;

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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getFacebookDraft(): ?string
    {
        return $this->facebookDraft;
    }

    public function setFacebookDraft(?string $facebookDraft): static
    {
        $facebookDraft = $facebookDraft !== null ? trim($facebookDraft) : null;
        $this->facebookDraft = $facebookDraft !== '' ? $facebookDraft : null;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /** @return Collection<int, BlogCategory> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(BlogCategory $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(BlogCategory $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function ensureSlug(): static
    {
        if ($this->slug !== '') {
            return $this;
        }

        $base = $this->slugify($this->title);
        $this->slug = $base !== '' ? $base : 'article';

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

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Blog article';
    }
}
