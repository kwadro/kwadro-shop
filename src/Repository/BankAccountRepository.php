<?php

namespace App\Repository;

use App\Entity\BankAccount;
use App\Entity\Locale;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BankAccount> */
class BankAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankAccount::class);
    }

    public function findPreferredForSiteAndLocale(Site $site, Locale $locale): ?BankAccount
    {
        $default = $this->createQueryBuilder('a')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->andWhere('a.is_default = :isDefault')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->setParameter('isDefault', true)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($default instanceof BankAccount) {
            return $default;
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function clearDefaultExcept(BankAccount $account): void
    {
        if ($account->getSite() === null || $account->getLocale() === null) {
            return;
        }

        $queryBuilder = $this->createQueryBuilder('a')
            ->update()
            ->set('a.is_default', ':false')
            ->andWhere('a.site = :site')
            ->andWhere('a.locale = :locale')
            ->andWhere('a.is_default = :true')
            ->setParameter('false', false)
            ->setParameter('true', true)
            ->setParameter('site', $account->getSite())
            ->setParameter('locale', $account->getLocale());

        if ($account->getId() !== null) {
            $queryBuilder
                ->andWhere('a.id != :id')
                ->setParameter('id', $account->getId());
        }

        $queryBuilder->getQuery()->execute();
    }
}
