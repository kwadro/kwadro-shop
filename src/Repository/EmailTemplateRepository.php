<?php

namespace App\Repository;

use App\Entity\EmailTemplate;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmailTemplate> */
class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    public function findOneBySiteAndName(Site $site, string $name): ?EmailTemplate
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return $this->findOneBy([
            'site' => $site,
            'name' => $name,
        ]);
    }
}
