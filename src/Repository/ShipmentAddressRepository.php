<?php

namespace App\Repository;

use App\Entity\ShipmentAddress;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ShipmentAddress> */
class ShipmentAddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShipmentAddress::class);
    }

    /** @return list<ShipmentAddress> */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('address')
            ->andWhere('address.user = :user')
            ->andWhere('address.cart IS NULL')
            ->andWhere('address.order IS NULL')
            ->setParameter('user', $user)
            ->orderBy('address.is_default', 'DESC')
            ->addOrderBy('address.updated_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ShipmentAddress> */
    public function findCatalogAddresses(User $user): array
    {
        return $this->findByUser($user);
    }

    public function findMatchingCatalogAddress(User $user, ShipmentAddress $source): ?ShipmentAddress
    {
        foreach ($this->findCatalogAddresses($user) as $address) {
            if ($address->matchesDelivery($source)) {
                return $address;
            }
        }

        return null;
    }

    public function findMatchingUserAddress(User $user, ShipmentAddress $source): ?ShipmentAddress
    {
        return $this->findMatchingCatalogAddress($user, $source);
    }
}
