<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\ShopPaymentMethod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Payment> */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findLatestPendingByOrder(Order $order): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :order')
            ->setParameter('order', $order)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestByOrder(Order $order): ?Payment
    {
        return $this->findLatestPendingByOrder($order);
    }

    public function findOneByGatewayReference(string $gatewayReference): ?Payment
    {
        return $this->findOneBy(['gateway_reference' => $gatewayReference]);
    }

    public function findOnDeliveryByOrder(Order $order): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :order')
            ->andWhere('p.method = :method')
            ->setParameter('order', $order)
            ->setParameter('method', ShopPaymentMethod::OnDelivery)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestPendingGatewayByOrder(Order $order): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.order = :order')
            ->andWhere('p.method IN (:methods)')
            ->andWhere('p.status = :status')
            ->setParameter('order', $order)
            ->setParameter('methods', [ShopPaymentMethod::Monobank, ShopPaymentMethod::Privatbank])
            ->setParameter('status', PaymentStatus::Pending)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
