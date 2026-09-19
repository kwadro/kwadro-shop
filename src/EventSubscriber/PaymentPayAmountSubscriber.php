<?php

namespace App\EventSubscriber;

use App\Entity\Payment;
use App\Entity\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Payment::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Payment::class)]
class PaymentPayAmountSubscriber
{
    public function prePersist(Payment $payment, PrePersistEventArgs $args): void
    {
        if ($payment->getStatus() !== PaymentStatus::Success) {
            return;
        }

        $payment->getOrder()?->addPayAmount($payment->getAmount());
    }

    public function preUpdate(Payment $payment, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('status')) {
            return;
        }

        $order = $payment->getOrder();
        if ($order === null) {
            return;
        }

        $previousStatus = $this->resolvePaymentStatus($args->getOldValue('status'));
        $currentStatus = $payment->getStatus();

        if ($previousStatus !== PaymentStatus::Success && $currentStatus === PaymentStatus::Success) {
            $order->addPayAmount($payment->getAmount());
        } elseif ($previousStatus === PaymentStatus::Success && $currentStatus !== PaymentStatus::Success) {
            $order->subtractPayAmount($payment->getAmount());
        }
    }

    private function resolvePaymentStatus(mixed $value): ?PaymentStatus
    {
        if ($value instanceof PaymentStatus) {
            return $value;
        }

        if (\is_string($value) && $value !== '') {
            return PaymentStatus::tryFrom($value);
        }

        return null;
    }
}
