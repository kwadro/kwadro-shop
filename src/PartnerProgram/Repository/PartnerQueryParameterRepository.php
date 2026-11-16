<?php

namespace App\PartnerProgram\Repository;

use App\PartnerProgram\Entity\PartnerQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PartnerQueryParameter> */
class PartnerQueryParameterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PartnerQueryParameter::class);
    }

    /**
     * Active query parameter names (original casing preserved for matching).
     *
     * @return list<string>
     */
    public function findActiveNames(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.name')
            ->andWhere('p.isActive = true')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $names = [];
        foreach ($rows as $row) {
            if (!\is_string($row)) {
                continue;
            }
            $name = trim($row);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
