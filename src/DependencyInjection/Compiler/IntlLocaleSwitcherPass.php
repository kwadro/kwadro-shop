<?php

namespace App\DependencyInjection\Compiler;

use App\Translation\IntlSafeLocaleSwitcher;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class IntlLocaleSwitcherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('translation.locale_switcher')) {
            return;
        }

        $container->getDefinition('translation.locale_switcher')
            ->setClass(IntlSafeLocaleSwitcher::class);
    }
}
