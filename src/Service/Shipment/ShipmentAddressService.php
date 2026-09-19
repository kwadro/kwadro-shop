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
            $this->entityManager->persist($address);

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

        $cart->setShipmentAddress(null);
        $cart->setDeliveryData(null);
        $address->setCart(null);
        $address->setOrder($order);
        $order->setShipmentAddress($address);
        $order->setDeliveryData($address->toDeliveryArray());

        return $address;
    }

    /** @param array<string, mixed> $deliveryData */
    public function attachDeliveryDataToOrder(Order $order, array $deliveryData): ?ShipmentAddress
    {
        if (($deliveryData['deliveryMethod'] ?? '') === '') {
            return null;
        }

        $address = ShipmentAddress::fromDeliveryArray($deliveryData);
        $address->setOrder($order);
        $order->setShipmentAddress($address);
        $order->setDeliveryData($address->toDeliveryArray());
        $this->entityManager->persist($address);

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
        }

        if ($orderAddress->getUser() === null) {
            $user->addShipmentAddress($orderAddress);
        }

        if ($orderAddress->getLabel() === null || $orderAddress->getLabel() === '') {
            $orderAddress->setLabel($orderAddress->getSummaryLabel());
        }

        return $orderAddress;
    }

    public function clearCartAddress(Cart $cart): void
    {
        $address = $cart->getShipmentAddress();
        if ($address === null) {
            $cart->setDeliveryData(null);

            return;
        }

        $cart->setShipmentAddress(null);
        $cart->setDeliveryData(null);
        $this->entityManager->remove($address);
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
        $this->entityManager->persist($clone);

        return $clone;
    }
}
