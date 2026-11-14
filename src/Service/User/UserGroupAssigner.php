<?php

namespace App\Service\User;

use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserRole;
use App\Repository\UserGroupRepository;
use App\Repository\UserRoleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Assigns default groups and ensures ROLE_USER is always present on groups.
 */
final class UserGroupAssigner
{
    public function __construct(
        private readonly UserGroupRepository $userGroupRepository,
        private readonly UserRoleRepository $userRoleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function assignClientGroup(User $user): void
    {
        $group = $this->userGroupRepository->findClientGroup();
        if ($group === null) {
            return;
        }

        $user->setUserGroup($group);
    }

    public function assignSuperAdministratorGroup(User $user): void
    {
        $group = $this->userGroupRepository->findSuperAdministratorGroup();
        if ($group === null) {
            return;
        }

        $user->setUserGroup($group);
    }

    public function ensureUserRoleOnGroup(UserGroup $group): void
    {
        $userRole = $this->userRoleRepository->findOneByCode(User::ROLE_USER);
        if ($userRole === null) {
            $userRole = (new UserRole())
                ->setName('User')
                ->setCode(User::ROLE_USER);
            $this->entityManager->persist($userRole);
        }

        $group->addRole($userRole);
    }
}
