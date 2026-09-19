<?php

namespace App\Repository;

use App\Entity\EmailTemplateSection;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmailTemplateSection> */
class EmailTemplateSectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplateSection::class);
    }

    public function findOneBySiteAndName(Site $site, string $name): ?EmailTemplateSection
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return null;
        }

        return $this->findOneBy([
            'site' => $site,
            'name' => $name,
        ]);
    }
}
