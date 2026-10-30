<?php

namespace App\Repository;

use App\Entity\ProductFeedSetting;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductFeedSetting> */
class ProductFeedSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductFeedSetting::class);
    }

    public function findOneBySite(Site $site): ?ProductFeedSetting
    {
        return $this->findOneBy(['site' => $site]);
    }

    public function findFirstEnabled(): ?ProductFeedSetting
    {
        /** @var ProductFeedSetting|null $setting */
        $setting = $this->createQueryBuilder('s')
            ->andWhere('s.enabled = true')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $setting;
    }
}
