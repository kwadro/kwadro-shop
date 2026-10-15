<?php

namespace App\EventSubscriber;

use App\Entity\AdminAuditLog;
use App\Service\Admin\AdminAuditLogger;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdminEntityAuditSubscriber implements EventSubscriberInterface
{
    /** @var array<int, list<array{field: string, old: mixed, new: mixed}>> */
    private array $pendingUpdates = [];

    public function __construct(
        private readonly AdminAuditLogger $auditLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityUpdatedEvent::class => 'onBeforeUpdated',
            AfterEntityUpdatedEvent::class => 'onAfterUpdated',
            AfterEntityPersistedEvent::class => 'onAfterPersisted',
            AfterEntityDeletedEvent::class => 'onAfterDeleted',
        ];
    }

    public function onBeforeUpdated(BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if ($entity instanceof AdminAuditLog || !$this->auditLogger->isEnabled()) {
            return;
        }

        $this->pendingUpdates[spl_object_id($entity)] = $this->auditLogger->buildChangeSet($entity);
    }

    public function onAfterUpdated(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        $oid = spl_object_id($entity);
        $changes = $this->pendingUpdates[$oid] ?? null;
        unset($this->pendingUpdates[$oid]);

        if ($entity instanceof AdminAuditLog || !$this->auditLogger->isEnabled()) {
            return;
        }

        $this->auditLogger->log(AdminAuditLog::ACTION_UPDATE, $entity, $changes);
    }

    public function onAfterPersisted(AfterEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if ($entity instanceof AdminAuditLog || !$this->auditLogger->isEnabled()) {
            return;
        }

        $this->auditLogger->log(AdminAuditLog::ACTION_CREATE, $entity);
    }

    public function onAfterDeleted(AfterEntityDeletedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        if ($entity instanceof AdminAuditLog || !$this->auditLogger->isEnabled()) {
            return;
        }

        $this->auditLogger->log(AdminAuditLog::ACTION_DELETE, $entity);
    }
}
