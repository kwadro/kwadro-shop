<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\Cart\VisitorIdResolver;

/** @extends ServiceEntityRepository<Order> */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findOneByOrderNumber(string $orderNumber): ?Order
    {
        return $this->findOneBy(['order_number' => $orderNumber]);
    }

    public function findOneByIdAndStatus(int $id, OrderStatus $status): ?Order
    {
        return $this->findOneBy(['id' => $id, 'status' => $status]);
    }

    public function findLatestPaidForSession(?User $customer, ?string $visitorId): ?Order
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->andWhere('o.updated_at >= :since')
            ->setParameter('status', OrderStatus::Paid)
            ->setParameter('since', new \DateTimeImmutable('-24 hours'))
            ->orderBy('o.updated_at', 'DESC')
            ->setMaxResults(1);

        if ($customer !== null) {
            $qb->andWhere('o.customer = :customer')
                ->setParameter('customer', $customer);
        } elseif (\is_string($visitorId) && VisitorIdResolver::isValid($visitorId)) {
            $qb->andWhere('o.visitor_id = :visitorId')
                ->setParameter('visitorId', $visitorId);
        } else {
            return null;
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
