<?php

namespace App\Repository;

use App\Entity\HomepageSetting;
use App\Entity\Locale;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomepageSetting>
 */
class HomepageSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageSetting::class);
    }

    public function findOneBySiteAndLocale(Site $site, Locale $locale): ?HomepageSetting
    {
        return $this->findOneBy(['site' => $site, 'locale' => $locale]);
    }
}
