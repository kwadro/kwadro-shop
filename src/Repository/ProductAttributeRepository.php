<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\ProductAttribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductAttribute> */
class ProductAttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductAttribute::class);
    }

    /**
     * Filterable attributes for a category (via attribute group membership).
     *
     * @return list<ProductAttribute>
     */
    public function findFilterableForCategory(?Category $category): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.groupItems', 'i')
            ->innerJoin('i.group', 'g')
            ->addSelect('i', 'g')
            ->andWhere('a.show_in_filter = true')
            ->andWhere('i.show_in_filter = true')
            ->andWhere('i.deleted = false')
            ->orderBy('i.position', 'ASC')
            ->addOrderBy('a.name', 'ASC');

        if ($category instanceof Category) {
            $or = $qb->expr()->orX('g.category = :category');
            $qb->setParameter('category', $category);
            $externalId = $category->getExternalId();
            if ($externalId !== null) {
                $or->add('g.external_category_id = :externalCategoryId');
                $qb->setParameter('externalCategoryId', $externalId);
            }
            $qb->andWhere($or);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<ProductAttribute>
     */
    public function findSearchable(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.use_in_search = true')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?ProductAttribute
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findOneByName(string $name): ?ProductAttribute
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
