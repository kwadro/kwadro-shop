<?php

namespace App\EventSubscriber;

use App\Entity\AdminShopNotification;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\Payment;
use App\Service\Admin\AdminShopNotifyRecorder;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ShopAdminNotifySubscriber
{
    /** @var list<string> */
    private const IGNORED_FIELDS = [
        'updated_at',
        'created_at',
        'gateway_response',
        'result_data',
    ];

    public function __construct(
        private readonly AdminShopNotifyRecorder $recorder,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->isTracked($entity)) {
                $this->recorder->queueFromEntity($entity, AdminShopNotification::ACTION_CREATED);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->isTracked($entity)) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            foreach (self::IGNORED_FIELDS as $field) {
                unset($changeSet[$field]);
            }

            if ($changeSet === []) {
                continue;
            }

            // Carts change very often (items/contact). Notify only on status changes.
            if ($entity instanceof Cart && !isset($changeSet['status'])) {
                continue;
            }

            $this->recorder->queueFromEntity($entity, AdminShopNotification::ACTION_UPDATED);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->recorder->flushQueue();
    }

    private function isTracked(object $entity): bool
    {
        return $entity instanceof Cart
            || $entity instanceof Order
            || $entity instanceof Payment;
    }
}
