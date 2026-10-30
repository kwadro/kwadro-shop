<?php

namespace App\AiContentAgent\Repository;

use App\AiContentAgent\Entity\ContentAgentLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ContentAgentLog> */
class ContentAgentLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentAgentLog::class);
    }
}
