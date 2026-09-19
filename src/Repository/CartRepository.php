<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\CartStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Cart> */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findActiveByVisitorId(string $visitorId): ?Cart
    {
        return $this->findOneBy([
            'visitor_id' => $visitorId,
            'status' => CartStatus::Active,
        ]);
    }

    public function findActiveByCustomer(User $customer): ?Cart
    {
        return $this->findOneBy([
            'customer' => $customer,
            'status' => CartStatus::Active,
        ]);
    }

    public function findSuspendedByCustomer(User $customer): ?Cart
    {
        return $this->findOneBy(
            ['customer' => $customer, 'status' => CartStatus::Suspended],
            ['updated_at' => 'DESC'],
        );
    }

    /** @deprecated use findActiveByVisitorId */
    public function findOneByVisitorId(string $visitorId): ?Cart
    {
        return $this->findActiveByVisitorId($visitorId) ?? $this->findOneBy(['visitor_id' => $visitorId], ['updated_at' => 'DESC']);
    }

    /** @deprecated use findActiveByCustomer */
    public function findOneByCustomer(User $customer): ?Cart
    {
        return $this->findActiveByCustomer($customer) ?? $this->findOneBy(['customer' => $customer], ['updated_at' => 'DESC']);
    }

    public function findForOrder(?User $customer, ?string $visitorId): ?Cart
    {
        if ($customer !== null) {
            $cart = $this->findOneBy(['customer' => $customer], ['updated_at' => 'DESC']);
            if ($cart !== null) {
                return $cart;
            }
        }

        if (\is_string($visitorId) && $visitorId !== '') {
            return $this->findOneBy(['visitor_id' => $visitorId], ['updated_at' => 'DESC']);
        }

        return null;
    }
}
