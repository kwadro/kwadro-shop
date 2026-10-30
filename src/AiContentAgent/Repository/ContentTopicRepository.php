<?php

namespace App\AiContentAgent\Repository;

use App\AiContentAgent\Entity\ContentTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContentTopic> */
class ContentTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentTopic::class);
    }

    public function findOneByTopicKey(string $topicKey): ?ContentTopic
    {
        return $this->findOneBy(['topicKey' => $topicKey]);
    }
}
