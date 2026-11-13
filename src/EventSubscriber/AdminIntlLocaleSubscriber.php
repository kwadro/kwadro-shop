<?php

namespace App\EventSubscriber;

use App\Service\Intl\IntlLocaleMapper;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Re-assert ICU default locale before every EasyAdmin CRUD action
 * (Form DateTime transformers call \Locale::getDefault() directly).
 */
final class AdminIntlLocaleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [BeforeCrudActionEvent::class => 'onBeforeCrudAction'];
    }

    public function onBeforeCrudAction(BeforeCrudActionEvent $event): void
    {
        IntlLocaleMapper::applyDefault();
    }
}
