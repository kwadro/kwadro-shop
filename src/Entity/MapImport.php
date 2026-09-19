<?php

namespace App\Entity;

use App\Repository\MapImportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapImportRepository::class)]
class MapImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entity = null;

    #[ORM\Column(nullable: true)]
    private ?int $old_id = null;

    #[ORM\Column(nullable: true)]
    private ?int $new_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getOldId(): ?int
    {
        return $this->old_id;
    }

    public function setOldId(?int $old_id): static
    {
        $this->old_id = $old_id;

        return $this;
    }

    public function getNewId(): ?int
    {
        return $this->new_id;
    }

    public function setNewId(?int $new_id): static
    {
        $this->new_id = $new_id;

        return $this;
    }
}
