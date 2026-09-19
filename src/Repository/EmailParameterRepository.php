<?php

namespace App\Repository;

use App\Entity\EmailParameter;
use App\Entity\Locale;
use App\Entity\Site;
use App\Service\Mail\EmailParameterValueResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmailParameter> */
class EmailParameterRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EmailParameterValueResolver $valueResolver,
    ) {
        parent::__construct($registry, EmailParameter::class);
    }

    /** @return array<string, string> */
    public function findValueMapBySiteAndLocale(Site $site, Locale $locale): array
    {
        /** @var list<EmailParameter> $parameters */
        $parameters = $this->createQueryBuilder('p')
            ->andWhere('p.site = :site')
            ->andWhere('p.locale = :locale')
            ->setParameter('site', $site)
            ->setParameter('locale', $locale)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        $values = [];
        foreach ($parameters as $parameter) {
            $values[$parameter->getName()] = $this->valueResolver->resolve($parameter, $site);
        }

        return $values;
    }
}
