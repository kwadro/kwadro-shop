<?php

namespace App\Repository;

use App\Entity\BotUserAgentMarker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BotUserAgentMarker> */
class BotUserAgentMarkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BotUserAgentMarker::class);
    }

    /**
     * @return list<string>
     */
    public function findActiveMarkers(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.marker')
            ->andWhere('m.isActive = true')
            ->orderBy('m.marker', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $markers = [];
        foreach ($rows as $row) {
            if (!\is_string($row)) {
                continue;
            }
            $marker = mb_strtolower(trim($row));
            if ($marker !== '') {
                $markers[] = $marker;
            }
        }

        return array_values(array_unique($markers));
    }
}
