<?php

namespace App;

use App\DependencyInjection\Compiler\IntlLocaleSwitcherPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        date_default_timezone_set('Europe/Kyiv');

        parent::boot();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new IntlLocaleSwitcherPass());
    }
}
