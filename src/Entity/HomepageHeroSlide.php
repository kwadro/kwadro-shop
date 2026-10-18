<?php

namespace App\Entity;

use App\Repository\HomepageHeroSlideRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HomepageHeroSlideRepository::class)]
#[ORM\Table(name: 'shop_homepage_hero_slide')]
class HomepageHeroSlide
{
    public const DEFAULT_IMAGE_POSITION = 70;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HomepageSetting::class, inversedBy: 'heroSlides')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HomepageSetting $homepageSetting = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 999)]
    private int $position = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    // DB column lead_text — "lead" is reserved in MySQL.
    #[ORM\Column(name: 'lead_text', type: 'text', nullable: true)]
    private ?string $lead = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Horizontal focus for object-position (0–100%). */
    #[ORM\Column(options: ['default' => self::DEFAULT_IMAGE_POSITION])]
    #[Assert\Range(min: 0, max: 100)]
    private int $imagePosition = self::DEFAULT_IMAGE_POSITION;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $primaryCtaLabel = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $primaryCtaUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $secondaryCtaLabel = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $secondaryCtaUrl = null;

    /** Transient upload field (not persisted). */
    private ?UploadedFile $imageFile = null;

    /** Transient flag to clear stored image on save (not persisted). */
    private bool $removeImage = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHomepageSetting(): ?HomepageSetting
    {
        return $this->homepageSetting;
    }

    public function setHomepageSetting(?HomepageSetting $homepageSetting): static
    {
        $this->homepageSetting = $homepageSetting;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = max(0, min(999, $position ?? 0));

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = self::normalizeText($brand);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = self::normalizeText($title);

        return $this;
    }

    public function getLead(): ?string
    {
        return $this->lead;
    }

    public function setLead(?string $lead): static
    {
        $this->lead = self::normalizeText($lead);

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = self::normalizeText($image);

        return $this;
    }

    public function getImageFile(): ?UploadedFile
    {
        return $this->imageFile;
    }

    public function setImageFile(?UploadedFile $imageFile): static
    {
        $this->imageFile = $imageFile;
        if ($imageFile instanceof UploadedFile) {
            // A new file replaces a pending delete.
            $this->removeImage = false;
        }

        return $this;
    }

    public function isRemoveImage(): bool
    {
        return $this->removeImage;
    }

    public function setRemoveImage(bool $removeImage): static
    {
        $this->removeImage = $removeImage;

        return $this;
    }

    public function syncUploadedImage(string $projectDir): void
    {
        $uploadDir = rtrim($projectDir, '/').'/public/uploads/images';
        $file = $this->imageFile;

        if ($file instanceof UploadedFile && $file->isValid()) {
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException('Cannot create homepage hero upload directory.');
            }

            $previous = $this->image;
            $ext = $file->guessExtension() ?: 'jpg';
            $filename = 'hero-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$ext;
            $file->move($uploadDir, $filename);
            $this->image = $filename;
            $this->imageFile = null;
            $this->removeImage = false;
            $this->deleteStoredImageFile($uploadDir, $previous);

            return;
        }

        if ($this->removeImage) {
            $previous = $this->image;
            $this->image = null;
            $this->removeImage = false;
            $this->deleteStoredImageFile($uploadDir, $previous);
        }
    }

    private function deleteStoredImageFile(string $uploadDir, ?string $filename): void
    {
        $filename = $filename !== null ? trim($filename) : '';
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/')) {
            return;
        }
        if (!str_starts_with($filename, 'hero-')) {
            return;
        }

        $path = rtrim($uploadDir, '/').'/'.$filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function getImagePosition(): int
    {
        return $this->imagePosition;
    }

    public function setImagePosition(?int $imagePosition): static
    {
        $this->imagePosition = max(0, min(100, $imagePosition ?? self::DEFAULT_IMAGE_POSITION));

        return $this;
    }

    public function getPrimaryCtaLabel(): ?string
    {
        return $this->primaryCtaLabel;
    }

    public function setPrimaryCtaLabel(?string $primaryCtaLabel): static
    {
        $this->primaryCtaLabel = self::normalizeText($primaryCtaLabel);

        return $this;
    }

    public function getPrimaryCtaUrl(): ?string
    {
        return $this->primaryCtaUrl;
    }

    public function setPrimaryCtaUrl(?string $primaryCtaUrl): static
    {
        $this->primaryCtaUrl = self::normalizeText($primaryCtaUrl);

        return $this;
    }

    public function getSecondaryCtaLabel(): ?string
    {
        return $this->secondaryCtaLabel;
    }

    public function setSecondaryCtaLabel(?string $secondaryCtaLabel): static
    {
        $this->secondaryCtaLabel = self::normalizeText($secondaryCtaLabel);

        return $this;
    }

    public function getSecondaryCtaUrl(): ?string
    {
        return $this->secondaryCtaUrl;
    }

    public function setSecondaryCtaUrl(?string $secondaryCtaUrl): static
    {
        $this->secondaryCtaUrl = self::normalizeText($secondaryCtaUrl);

        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?: ($this->brand ?: sprintf('Hero slide #%s', $this->id ?? '?'));
    }

    private static function normalizeText(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
