<?php

namespace App\Repository;

use App\Entity\Redirect;
use App\Entity\RedirectType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Redirect> */
class RedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Redirect::class);
    }

    public function findReadyByFromPath(string $fromPath): ?Redirect
    {
        $fromPath = Redirect::normalizePath($fromPath);

        /** @var Redirect|null $redirect */
        $redirect = $this->createQueryBuilder('r')
            ->andWhere('r.fromPath = :fromPath')
            ->andWhere('r.enabled = true')
            ->andWhere('r.toPath IS NOT NULL')
            ->andWhere("r.toPath <> ''")
            ->setParameter('fromPath', $fromPath)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $redirect;
    }

    public function findOneByFromPath(string $fromPath): ?Redirect
    {
        return $this->findOneBy(['fromPath' => Redirect::normalizePath($fromPath)]);
    }

    public function countByType(RedirectType $type): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
