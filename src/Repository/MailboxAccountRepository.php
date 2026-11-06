<?php

namespace App\Repository;

use App\Entity\MailboxAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MailboxAccount> */
class MailboxAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailboxAccount::class);
    }

    /** @return list<MailboxAccount> */
    public function findActive(): array
    {
        /** @var list<MailboxAccount> $items */
        $items = $this->createQueryBuilder('m')
            ->andWhere('m.isActive = true')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function findOneActiveByEmail(string $email): ?MailboxAccount
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        /** @var MailboxAccount|null $account */
        $account = $this->createQueryBuilder('m')
            ->andWhere('LOWER(m.email) = :email')
            ->andWhere('m.isActive = true')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }
}
