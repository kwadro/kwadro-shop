<?php

namespace App\Service\Admin;

use App\Entity\AdminShopNotification;
use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\AdminShopNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminShopNotifyRecorder
{
    private bool $writing = false;

    /** @var list<array{entity: object, action: string}> */
    private array $queue = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminShopNotificationRepository $notificationRepository,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function queueFromEntity(object $entity, string $action): void
    {
        if (!$entity instanceof Cart && !$entity instanceof Order && !$entity instanceof Payment) {
            return;
        }

        $this->queue[] = [
            'entity' => $entity,
            'action' => $action,
        ];
    }

    /**
     * Toast when today's RequestList count hits 100, 200, 300, …
     */
    public function recordRequestListMilestone(int $todayCount): void
    {
        if ($todayCount <= 0 || $todayCount % 100 !== 0) {
            return;
        }

        $timezone = new \DateTimeZone(date_default_timezone_get() ?: 'Europe/Kyiv');
        $dayKey = (int) (new \DateTimeImmutable('today', $timezone))->format('Ymd');
        $step = (int) ($todayCount / 100);
        $entityId = ($dayKey * 1000) + $step;

        if ($this->notificationRepository->existsFor(AdminShopNotification::CHANNEL_REQUESTS, $entityId)) {
            return;
        }

        if ($this->writing) {
            return;
        }

        $this->writing = true;
        try {
            $notification = (new AdminShopNotification())
                ->setChannel(AdminShopNotification::CHANNEL_REQUESTS)
                ->setAction(AdminShopNotification::ACTION_MILESTONE)
                ->setEntityId($entityId)
                ->setTitle($this->translator->trans('admin.shop_notify.requests_milestone_title', [], 'messages'))
                ->setBody($this->translator->trans(
                    'admin.shop_notify.requests_milestone_body',
                    ['%count%' => $todayCount],
                    'messages',
                ))
                ->setTargetPath($this->urlGenerator->generate('admin_request_list_index', ['_locale' => 'uk']))
                ->setIsNotified(false);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();
        } catch (\Throwable) {
            // Never break request logging because of toast notifications.
        } finally {
            $this->writing = false;
        }
    }

    public function flushQueue(): void
    {
        if ($this->writing || $this->queue === []) {
            return;
        }

        $items = $this->queue;
        $this->queue = [];
        $this->writing = true;

        try {
            foreach ($items as $item) {
                $payload = $this->buildPayload($item['entity'], $item['action']);
                if ($payload === null) {
                    continue;
                }

                $existing = $this->notificationRepository->findPendingFor($payload['channel'], $payload['entityId']);
                if ($existing instanceof AdminShopNotification) {
                    $existing
                        ->setAction($payload['action'])
                        ->setTitle($payload['title'])
                        ->setBody($payload['body'])
                        ->setTargetPath($payload['targetPath']);
                    continue;
                }

                $notification = (new AdminShopNotification())
                    ->setChannel($payload['channel'])
                    ->setAction($payload['action'])
                    ->setEntityId($payload['entityId'])
                    ->setTitle($payload['title'])
                    ->setBody($payload['body'])
                    ->setTargetPath($payload['targetPath'])
                    ->setIsNotified(false);

                $this->entityManager->persist($notification);
            }

            $this->entityManager->flush();
        } catch (\Throwable) {
            // Never break checkout / admin CRUD because of toast notifications.
        } finally {
            $this->writing = false;
        }
    }

    /**
     * @return array{channel: string, action: string, entityId: int, title: string, body: string, targetPath: string}|null
     */
    private function buildPayload(object $entity, string $action): ?array
    {
        if ($entity instanceof Cart) {
            $id = (int) $entity->getId();
            if ($id <= 0) {
                return null;
            }

            return [
                'channel' => AdminShopNotification::CHANNEL_CART,
                'action' => $action,
                'entityId' => $id,
                'title' => $this->translator->trans(
                    $action === AdminShopNotification::ACTION_CREATED
                        ? 'admin.shop_notify.cart_created_title'
                        : 'admin.shop_notify.cart_updated_title',
                    [],
                    'messages',
                ),
                'body' => $this->translator->trans(
                    'admin.shop_notify.cart_body',
                    [
                        '%id%' => $id,
                        '%status%' => $entity->getStatus()->value,
                    ],
                    'messages',
                ),
                'targetPath' => $this->urlGenerator->generate('admin_cart_detail', [
                    '_locale' => 'uk',
                    'entityId' => $id,
                ]),
            ];
        }

        if ($entity instanceof Order) {
            $id = (int) $entity->getId();
            if ($id <= 0) {
                return null;
            }

            return [
                'channel' => AdminShopNotification::CHANNEL_ORDER,
                'action' => $action,
                'entityId' => $id,
                'title' => $this->translator->trans(
                    $action === AdminShopNotification::ACTION_CREATED
                        ? 'admin.shop_notify.order_created_title'
                        : 'admin.shop_notify.order_updated_title',
                    [],
                    'messages',
                ),
                'body' => $this->translator->trans(
                    'admin.shop_notify.order_body',
                    [
                        '%number%' => $entity->getOrderNumber(),
                        '%status%' => $entity->getStatus()->value,
                        '%amount%' => number_format($entity->getAmount(), 2, '.', ''),
                    ],
                    'messages',
                ),
                'targetPath' => $this->urlGenerator->generate('admin_order_detail', [
                    '_locale' => 'uk',
                    'entityId' => $id,
                ]),
            ];
        }

        if ($entity instanceof Payment) {
            $id = (int) $entity->getId();
            if ($id <= 0) {
                return null;
            }

            return [
                'channel' => AdminShopNotification::CHANNEL_PAYMENT,
                'action' => $action,
                'entityId' => $id,
                'title' => $this->translator->trans(
                    $action === AdminShopNotification::ACTION_CREATED
                        ? 'admin.shop_notify.payment_created_title'
                        : 'admin.shop_notify.payment_updated_title',
                    [],
                    'messages',
                ),
                'body' => $this->translator->trans(
                    'admin.shop_notify.payment_body',
                    [
                        '%id%' => $id,
                        '%order%' => $entity->getOrder()?->getOrderNumber() ?? '—',
                        '%status%' => $entity->getStatus()->value,
                        '%amount%' => number_format($entity->getAmount(), 2, '.', ''),
                    ],
                    'messages',
                ),
                'targetPath' => $this->urlGenerator->generate('admin_payment_detail', [
                    '_locale' => 'uk',
                    'entityId' => $id,
                ]),
            ];
        }

        return null;
    }
}
