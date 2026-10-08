<?php

namespace App\Repository;

use App\Entity\ProductSearchSetting;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ProductSearchSetting> */
class ProductSearchSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductSearchSetting::class);
    }

    public function findOneBySite(Site $site): ?ProductSearchSetting
    {
        return $this->findOneBy(['site' => $site]);
    }

    public function findOneByDomain(string $domain): ?ProductSearchSetting
    {
        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        return $this->createQueryBuilder('s')
            ->innerJoin('s.site', 'site')
            ->andWhere('site.domain = :domain')
            ->setParameter('domain', $domain)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<string>
     */
    public function resolveSearchFieldsForDomain(?string $domain): array
    {
        if ($domain !== null && $domain !== '') {
            $setting = $this->findOneByDomain($domain);
            if ($setting !== null) {
                return $setting->getSearchFields();
            }
        }

        return ProductSearchSetting::DEFAULT_FIELDS;
    }
}
