<?php

namespace App\Service\Shipment;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\ShipmentAddress;
use App\Entity\User;
use App\Repository\ShipmentAddressRepository;
use Doctrine\ORM\EntityManagerInterface;

class ShipmentAddressService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentAddressRepository $shipmentAddressRepository,
    ) {
    }

    /** @param array<string, mixed> $deliveryData */
    public function syncCartAddress(Cart $cart, array $deliveryData): ShipmentAddress
    {
        $address = $cart->getShipmentAddress();
        if ($address === null) {
            $address = ShipmentAddress::fromDeliveryArray($deliveryData);
            $address->setCart($cart);
            $cart->setShipmentAddress($address);
            // Cart.shipmentAddress has cascade persist; avoid explicit persist on a managed entity.

            return $address;
        }

        $address->applyDeliveryArray($deliveryData);

        return $address;
    }

    public function attachCartAddressToOrder(Cart $cart, Order $order): ?ShipmentAddress
    {
        $address = $cart->getShipmentAddress();
        if ($address === null) {
            $legacy = $cart->getDeliveryData();
            if (!\is_array($legacy) || ($legacy['deliveryMethod'] ?? '') === '') {
                return null;
            }

            return $this->attachDeliveryDataToOrder($order, $legacy);
        }

        // Clone onto the order. Moving the same row triggers Cart orphanRemoval
        // (delete) + Order cascade persist (insert) → managed+dirty insert error.
        $orderAddress = $address->cloneAsNew();
        $orderAddress->setOrder($order);
        $order->setShipmentAddress($orderAddress);
        $order->setDeliveryData($orderAddress->toDeliveryArray());

        $this->clearCartAddress($cart);

        return $orderAddress;
    }

    /** @param array<string, mixed> $deliveryData */
    public function attachDeliveryDataToOrder(Order $order, array $deliveryData): ?ShipmentAddress
    {
        if (($deliveryData['deliveryMethod'] ?? '') === '') {
            return null;
        }

        $existing = $order->getShipmentAddress();
        if ($existing !== null) {
            $existing->applyDeliveryArray($deliveryData);
            $order->setDeliveryData($existing->toDeliveryArray());

            return $existing;
        }

        $address = ShipmentAddress::fromDeliveryArray($deliveryData);
        $address->setOrder($order);
        $order->setShipmentAddress($address);
        $order->setDeliveryData($address->toDeliveryArray());

        return $address;
    }

    public function saveOrderAddressToUserAccount(Order $order, ?User $user = null): ?ShipmentAddress
    {
        $user = $user ?? $order->getCustomer();
        if ($user === null) {
            return null;
        }

        $orderAddress = $order->getShipmentAddress();
        if ($orderAddress === null) {
            $legacy = $order->getDeliveryData();
            if (!\is_array($legacy) || ($legacy['deliveryMethod'] ?? '') === '') {
                return null;
            }

            $orderAddress = $this->attachDeliveryDataToOrder($order, $legacy);
            if ($orderAddress === null) {
                return null;
            }
        }

        $catalogAddress = $this->shipmentAddressRepository->findMatchingCatalogAddress($user, $orderAddress);
        if ($catalogAddress !== null) {
            $catalogAddress->applyDeliveryArray($orderAddress->toDeliveryArray());
            if ($catalogAddress->getLabel() === null || $catalogAddress->getLabel() === '') {
                $catalogAddress->setLabel($orderAddress->getSummaryLabel());
            }

            return $catalogAddress;
        }

        // Keep order address on the order; store a separate catalog copy on the user.
        $saved = $orderAddress->cloneAsNew();
        $saved->setLabel($orderAddress->getSummaryLabel());
        $user->addShipmentAddress($saved);

        return $saved;
    }

    public function clearCartAddress(Cart $cart): void
    {
        $address = $cart->getShipmentAddress();
        $cart->setDeliveryData(null);
        if ($address === null) {
            return;
        }

        $address->setCart(null);
        $cart->setShipmentAddress(null);
        if ($this->entityManager->contains($address)) {
            $this->entityManager->remove($address);
        }
    }

    public function cloneAddressForCart(ShipmentAddress $source, Cart $target): ShipmentAddress
    {
        if ($target->getShipmentAddress() !== null) {
            $this->clearCartAddress($target);
        }

        $clone = $source->cloneAsNew();
        $clone->setCart($target);
        $target->setShipmentAddress($clone);
        $target->setDeliveryData($clone->toDeliveryArray());

        return $clone;
    }
}
