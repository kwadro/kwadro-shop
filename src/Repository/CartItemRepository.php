<?php

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\CartItemStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CartItem> */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function findActiveByCartAndOffer(Cart $cart, int $offerId): ?CartItem
    {
        if ($offerId <= 0 || $cart->getId() === null) {
            return null;
        }

        return $this->findOneBy([
            'cart' => $cart,
            'offer_id' => $offerId,
            'status' => CartItemStatus::Active,
        ]);
    }

    /**
     * @return list<CartItem>
     */
    public function findActiveByCartAndOfferAll(Cart $cart, int $offerId): array
    {
        if ($offerId <= 0 || $cart->getId() === null) {
            return [];
        }

        return $this->findBy([
            'cart' => $cart,
            'offer_id' => $offerId,
            'status' => CartItemStatus::Active,
        ], ['id' => 'ASC']);
    }

    public function findActiveByCartSupplierSku(Cart $cart, int $supplierId, string $sku): ?CartItem
    {
        $sku = trim($sku);
        if ($supplierId <= 0 || $sku === '' || $cart->getId() === null) {
            return null;
        }

        return $this->findOneBy([
            'cart' => $cart,
            'supplier_id' => $supplierId,
            'product_sku' => $sku,
            'status' => CartItemStatus::Active,
        ]);
    }
}
