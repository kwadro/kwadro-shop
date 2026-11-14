<?php

namespace App\Repository;

use App\Entity\UserGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserGroup> */
class UserGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserGroup::class);
    }

    public function findOneByCode(string $code): ?UserGroup
    {
        return $this->findOneBy(['code' => strtolower(trim($code))]);
    }

    public function findClientGroup(): ?UserGroup
    {
        return $this->findOneByCode(UserGroup::CODE_CLIENT);
    }

    public function findSuperAdministratorGroup(): ?UserGroup
    {
        return $this->findOneByCode(UserGroup::CODE_SUPER_ADMINISTRATOR);
    }
}
