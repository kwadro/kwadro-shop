<?php

namespace App\Service;


use App\Entity\MapImport;
use Doctrine\ORM\EntityManagerInterface;

class MapIdImporter
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function addItem(mixed $oldId, mixed $newId, string $entityClass)
    {
        $entity = new MapImport;
        $entity->setOldId($oldId);
        $entity->setNewId($newId);
        $entity->setEntity($entityClass);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function loadNewId(mixed $oldId, string $entityClass)
    {
        $entity = $this->em->getRepository(MapImport::class)->findOneBy(['old_id' => $oldId, 'entity' => $entityClass]);
        return $entity?->getNewId();
    }
}
