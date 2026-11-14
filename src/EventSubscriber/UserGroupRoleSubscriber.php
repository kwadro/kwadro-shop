<?php

namespace App\EventSubscriber;

use App\Entity\UserGroup;
use App\Service\User\UserGroupAssigner;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class UserGroupRoleSubscriber
{
    public function __construct(
        private readonly UserGroupAssigner $userGroupAssigner,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof UserGroup) {
            $this->userGroupAssigner->ensureUserRoleOnGroup($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof UserGroup) {
            $this->userGroupAssigner->ensureUserRoleOnGroup($entity);
        }
    }
}
