<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Common\Collections\Collection;

readonly class CsvEntityExporter
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * @param class-string $entityClass
     */
    public function export(string $entityClass, string $filePath): void
    {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException("Entity $entityClass does not exist");
        }

        $meta = $this->em->getClassMetadata($entityClass);
        $repo = $this->em->getRepository($entityClass);

        $handle = fopen($filePath, 'w');

        // ---- CSV HEADER ----
        $headers = array_merge(
            $meta->getFieldNames(),
            array_keys($meta->getAssociationMappings())
        );

        fputcsv($handle, $headers,';');

        // ---- DATA ----
        $query = $repo->createQueryBuilder('e')->getQuery();

        foreach ($query->toIterable() as $entity) {
            $row = [];

            // scalar fields
            foreach ($meta->getFieldNames() as $field) {
                $value = $meta->getFieldValue($entity, $field);
                $row[] = $this->normalizeValue($value);
            }

            // associations
            foreach ($meta->getAssociationMappings() as $assoc) {
                $value = $meta->getFieldValue($entity, $assoc['fieldName']);
                $row[] = $this->normalizeAssociation($value);
            }

            fputcsv($handle, $row,';');
            $this->em->clear();
        }

        fclose($handle);
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if ( method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    private function normalizeAssociation(mixed $value): string
    {
        // toMany
        if ($value instanceof Collection) {
            $items = [];

            foreach ($value as $item) {
                $items[] = method_exists($item, '__toString')
                    ? (string) $item
                    : (string) $item->getId();
            }

            return implode(', ', $items);
        }

        // toOne
        if (is_object($value)) {
            if(get_class($value) == 'Proxies\__CG__\App\Entity\GroupComponent'){
                return (string) $value->getId();
            }
            return method_exists($value, '__toString')
                ? (string) $value
                : (string) $value->getId();
        }

        return '';
    }
}
