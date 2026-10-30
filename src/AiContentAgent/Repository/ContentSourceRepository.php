<?php

namespace App\AiContentAgent\Repository;

use App\AiContentAgent\Entity\ContentSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContentSource> */
class ContentSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentSource::class);
    }

    public function findOneByUrlHash(string $urlHash): ?ContentSource
    {
        return $this->findOneBy(['urlHash' => $urlHash]);
    }

    /** @return list<ContentSource> */
    public function findByTopicKey(string $topicKey): array
    {
        /** @var list<ContentSource> $items */
        $items = $this->createQueryBuilder('s')
            ->andWhere('s.topicKey = :topicKey')
            ->setParameter('topicKey', $topicKey)
            ->orderBy('s.discoveredAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }
}
