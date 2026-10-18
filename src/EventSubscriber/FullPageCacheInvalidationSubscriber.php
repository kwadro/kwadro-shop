<?php

namespace App\EventSubscriber;

use App\Entity;
use App\Service\Cache\FullPageCachePurger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Drops the nginx full page cache once per request/command when storefront content changed.
 * Bulk imports flush many times; the purge itself runs only at terminate.
 */
#[AsDoctrineListener(event: Events::onFlush)]
class FullPageCacheInvalidationSubscriber implements EventSubscriberInterface
{
    /** @var list<class-string> */
    private const STOREFRONT_ENTITIES = [
        Entity\Product::class,
        Entity\ProductOffer::class,
        Entity\ProductAttribute::class,
        Entity\ProductAttributeGroup::class,
        Entity\ProductAttributeValue::class,
        Entity\ProductSearchSetting::class,
        Entity\Category::class,
        Entity\Supplier::class,
        Entity\BlogArticle::class,
        Entity\BlogCategory::class,
        Entity\HomepageSetting::class,
        Entity\HomepageHeroSlide::class,
        Entity\HomepageCategoryItem::class,
        Entity\ShopMegaMenuSetting::class,
        Entity\ShopMegaMenuItem::class,
        Entity\Site::class,
        Entity\Locale::class,
        Entity\SeoSetting::class,
        Entity\SeoSettingsTranslation::class,
        Entity\HeaderSetting::class,
        Entity\HeaderTranslation::class,
        Entity\FooterSetting::class,
        Entity\FooterTranslation::class,
        Entity\MegaMenuSetting::class,
        Entity\MegaMenuTranslation::class,
        Entity\Popularsearch::class,
        Entity\Redirect::class,
    ];

    public function __construct(
        private readonly FullPageCachePurger $purger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'purge',
            ConsoleEvents::TERMINATE => 'purge',
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->purger->isConfigured()) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();
        $changed = [
            ...$uow->getScheduledEntityInsertions(),
            ...$uow->getScheduledEntityUpdates(),
            ...$uow->getScheduledEntityDeletions(),
        ];

        foreach ($changed as $entity) {
            foreach (self::STOREFRONT_ENTITIES as $class) {
                if ($entity instanceof $class) {
                    $this->purger->schedule();

                    return;
                }
            }
        }

        if ($uow->getScheduledCollectionUpdates() !== [] || $uow->getScheduledCollectionDeletions() !== []) {
            foreach ([...$uow->getScheduledCollectionUpdates(), ...$uow->getScheduledCollectionDeletions()] as $collection) {
                $owner = $collection->getOwner();
                foreach (self::STOREFRONT_ENTITIES as $class) {
                    if ($owner instanceof $class) {
                        $this->purger->schedule();

                        return;
                    }
                }
            }
        }
    }

    public function purge(): void
    {
        $this->purger->purgeIfScheduled();
    }
}
