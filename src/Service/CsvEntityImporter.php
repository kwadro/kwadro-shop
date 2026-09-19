<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\String\Inflector\EnglishInflector;

readonly class CsvEntityImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private MapIdImporter $mapIdImporter
    ) {
    }

    public function importScalars(string $entityClass, string $csvPath): void
    {
        $repo = $this->em->getRepository($entityClass);
        $meta = $this->em->getClassMetadata($entityClass);
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle,null,';');
        $idIndex = array_search('id', $header);
        while (($row = fgetcsv($handle,null,';')) !== false) {
            $oldId = $row[$idIndex] ?? null;
            if(!$oldId){
                continue;
            }
            $newId = $this->mapIdImporter->loadNewId($oldId, $entityClass);
            $isNew = false;
            if (!$newId) {
                // create new entity
                $entity = new $entityClass();
                $entity->setId(null);
                $isNew = true;
            } else {
                $entity = $repo->find($newId);
                if(!$entity){
                    continue;
                }
            }
            foreach ($header as $i => $column) {
                if($column === 'id'){
                    continue;
                }
                $value = $row[$i] ?? null;

                // scalar fields only
                if ($meta->hasField($column)) {
                    $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $column)));
                    if (method_exists($entity, $setter)) {
                        $value = $this->castValue($meta, $column, $value);
                        $entity->$setter($value);
                    }
                }
            }
            if ($isNew) {
                $this->em->persist($entity);
                $this->em->flush();
                //save map data
                $newId = $entity->getId();
                $this->mapIdImporter->addItem($oldId, $newId, $entityClass);
            } else {
                $this->em->flush();
            }
            $this->em->clear();
        }
        fclose($handle);
    }
    private function castValue(ClassMetadata $meta, string $column, mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($meta->hasField($column)) {
            $type = $meta->getTypeOfField($column);

            return match ($type) {
                'datetime_immutable' => new \DateTimeImmutable($value),
                'datetime', 'datetimetz' => new \DateTime($value),
                'date'                  => new \DateTime($value),
                'integer', 'smallint'   => (int) $value,
                'boolean'               => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'float', 'decimal'      => (float) $value,
                'json'                 => json_decode($value,true),
                default                 => $value,
            };
        }

        return $value;
    }
    private function singularize(string $plural): string
    {
        $inflector = new EnglishInflector();
        $singulars = $inflector->singularize($plural);
        $singular =  $singulars[0] ?? $plural;
        return ucfirst($singular);
    }


    /**
     * @param class-string $entityClass
     */
    public function importAssociations(string $entityClass, string $csvPath): void
    {
        $meta = $this->em->getClassMetadata($entityClass);
        $repo = $this->em->getRepository($entityClass);

        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle,null,';');
        $associationFieldMap = [
            'products' => 'name',
            'category' => 'name',
            'orderitems' => 'sku',
            'tags' => 'name',
            'sale' => 'incrementid',
            'source' => 'name',
            'client' => 'phone',
            'recipecategorys'=>'name',
            'recipetranslations'=>'name',
            'recipetranslation'=>'name',
            'recipeauthor'=>'name',
            'components'=>'id',
            'groupcomponents'=>'id',
            'groupcomponent'=>'id',
            'recipesteps'=>'name',
            'children'=>'name',
            'parent'=>'name',
            'ingredient'=>'name',
            'unit'=>'short_name',
            'site'=>'domain',
            'locale'=>'name',
            'headersetting'=>'site',
            'seosetting'=>'site',
            'footersetting'=>'site',
            'megamenutype'=>'name',
            'megamenusetting'=>'site',
            'user'=>'email',
            'filtergroup'=>'filtergroupname',
            'mailbox'=>'boxname',
            'emailmailbox'=>'boxname'
        ];
        $idIndex = array_search('id', $header);
        while (($row = fgetcsv($handle,null,';')) !== false) {
            // assume "id" column exists
            $oldId = $row[$idIndex];
            $id = $this->mapIdImporter->loadNewId($oldId, $entityClass);
            if(!$id){
                continue;
            }
            $entity = $repo->find($id);
            if (!$entity) {
                continue;
            }

            foreach ($header as $i => $column) {
                $value = $row[$i] ?? null;
                // only associations
                if ($meta->hasAssociation($column)) {
                    $assocClass = $meta->getAssociationTargetClass($column);
                    $assocRepo = $this->em->getRepository($assocClass);
                    if ($meta->isSingleValuedAssociation($column)) {
                        $fieldToSearch = $associationFieldMap[$column] ?? 'id';
                        $meta2 = $this->em->getClassMetadata($assocClass);
                        if($meta2->hasAssociation($fieldToSearch)){
                            $assocClass2 = $meta2->getAssociationTargetClass($fieldToSearch);
                            $assocRepo2 = $this->em->getRepository($assocClass2);
                            $fieldToSearch2 = $associationFieldMap[$fieldToSearch] ?? 'id';
                            $assocEntity2 = $assocRepo2->findOneBy([$fieldToSearch2 => $value]);
                            $value = $assocEntity2->getId();
                        }
                        echo get_class($assocRepo) . ' - ' . $column . ' - ' . $fieldToSearch . ' - ' . $value  . PHP_EOL;
                        $assocEntity = $assocRepo->findOneBy([$fieldToSearch => $value]);
                        echo $assocEntity?->getId() ?? 'empty' . PHP_EOL;
                        echo get_class($entity) . PHP_EOL;
                        if ($assocEntity) {
                            $setter = 'set' . ucfirst($column);
                            $entity->$setter($assocEntity);
                        }

                    } elseif ($meta->isCollectionValuedAssociation($column)) {
                        $ids = explode(', ', $value);
                        $assocEntities = [];
                        foreach ($ids as $assocId) {
                            $fieldToSearch = $associationFieldMap[$column] ?? 'id';
                            echo $assocClass . '-' . $column . '-' . $fieldToSearch . '-' .$value . PHP_EOL;
                            $assocEntity = $assocRepo->findOneBy([$fieldToSearch => $assocId]);
                            if ($assocEntity) {
                                $assocEntities[] = $assocEntity;
                            }
                        }
                        // Todo: need change singular from plural
                        $adder = 'add' . $this->singularize($column);
                        foreach ($assocEntities as $assocEntity) {
                            $entity->$adder($assocEntity);
                        }
                    }
                }
            }
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->em->clear();
        fclose($handle);
    }

    public function setSiteUrl($appDomain): void
    {
        $siteRepository = $this->em->getRepository('App\Entity\Site');
        $site = $siteRepository->find(1);
        $site->setDomain($appDomain);
        $this->em->persist($site);
        $this->em->flush();
    }
}

