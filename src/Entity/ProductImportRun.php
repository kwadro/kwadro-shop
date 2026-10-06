<?php

namespace App\Entity;

use App\Repository\ProductImportRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductImportRunRepository::class)]
#[ORM\Table(name: 'shop_product_import_run')]
class ProductImportRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductImport::class, inversedBy: 'runs')]
    #[ORM\JoinColumn(name: 'product_import_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ProductImport $productImport = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $started_at;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finished_at = null;

    #[ORM\Column(length: 16, enumType: ProductImportRunStatus::class)]
    private ProductImportRunStatus $status = ProductImportRunStatus::Running;

    #[ORM\Column(options: ['default' => 0])]
    private int $created_count = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $updated_count = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $disabled_count = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $deleted_count = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $result = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error_message = null;

    public function __construct()
    {
        $this->started_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductImport(): ?ProductImport
    {
        return $this->productImport;
    }

    public function setProductImport(?ProductImport $productImport): static
    {
        $this->productImport = $productImport;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->started_at;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->started_at = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finished_at;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finished_at = $finishedAt;

        return $this;
    }

    public function getStatus(): ProductImportRunStatus
    {
        return $this->status;
    }

    public function setStatus(ProductImportRunStatus|string $status): static
    {
        if (\is_string($status)) {
            $status = ProductImportRunStatus::from($status);
        }
        $this->status = $status;

        return $this;
    }

    public function getCreatedCount(): int
    {
        return $this->created_count;
    }

    public function setCreatedCount(int $createdCount): static
    {
        $this->created_count = max(0, $createdCount);

        return $this;
    }

    public function getUpdatedCount(): int
    {
        return $this->updated_count;
    }

    public function setUpdatedCount(int $updatedCount): static
    {
        $this->updated_count = max(0, $updatedCount);

        return $this;
    }

    public function getDisabledCount(): int
    {
        return $this->disabled_count;
    }

    public function setDisabledCount(int $disabledCount): static
    {
        $this->disabled_count = max(0, $disabledCount);

        return $this;
    }

    public function getDeletedCount(): int
    {
        return $this->deleted_count;
    }

    public function setDeletedCount(int $deletedCount): static
    {
        $this->deleted_count = max(0, $deletedCount);

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result !== null ? trim($result) : null;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->error_message;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->error_message = $errorMessage !== null ? trim($errorMessage) : null;

        return $this;
    }

    public function getSummary(): string
    {
        if ($this->result !== null && $this->result !== '') {
            return $this->result;
        }

        return sprintf(
            'created=%d, updated=%d, disabled=%d, deleted=%d',
            $this->created_count,
            $this->updated_count,
            $this->disabled_count,
            $this->deleted_count,
        );
    }

    public function __toString(): string
    {
        $name = $this->productImport?->getName() ?? 'Import';

        return sprintf('%s @ %s', $name, $this->started_at->format('Y-m-d H:i'));
    }
}
