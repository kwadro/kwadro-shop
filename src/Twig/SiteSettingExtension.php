<?php

namespace App\Twig;

use App\Service\SiteSettingsProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

use Twig\TwigFunction;

use function Symfony\Component\String\s;

class SiteSettingExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private SiteSettingsProvider $provider
    ) {
    }
    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_settings', [$this, 'getSettings']),
        ];
    }
    public function getSettings(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return [];
        }

        return  $this->provider->getSettings(
            $request->getHost(),
            $request->getLocale()
        );
    }
}
