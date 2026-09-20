<?php

namespace App\Controller;

use App\Routing\ShopRoutes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocaleRedirectController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    #[Route('/', name: 'shop_root_redirect')]
    public function redirectRoot(): RedirectResponse
    {
        return $this->redirectToRoute('shop_home', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/login', name: 'app_login_root_redirect')]
    public function redirectLogin(): RedirectResponse
    {
        return $this->redirectToRoute('app_login', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/logout', name: 'app_logout_root_redirect')]
    public function redirectLogout(): RedirectResponse
    {
        return $this->redirectToRoute('app_logout', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/register', name: 'app_register_root_redirect')]
    public function redirectRegister(): RedirectResponse
    {
        return $this->redirectToRoute('app_register', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/checkout', name: 'shop_checkout_root_redirect')]
    public function redirectCheckout(): RedirectResponse
    {
        return $this->redirectToRoute('shop_checkout', ['_locale' => $this->defaultLocale]);
    }

    #[Route('/admser', name: 'shop_admser_root_redirect')]
    public function redirectAdmser(): RedirectResponse
    {
        $response = $this->redirectToRoute('shop_admser', ['_locale' => $this->defaultLocale]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');

        return $response;
    }

    #[Route('/robots.txt', name: 'shop_robots_txt')]
    public function robotsTxt(UrlGeneratorInterface $urlGenerator): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admser',
            'Disallow: /uk/admser',
            'Disallow: /en/admser',
            'Disallow: /checkout',
            'Disallow: /uk/checkout',
            'Disallow: /en/checkout',
            '',
            'Sitemap: '.$urlGenerator->generate('shop_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '',
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    #[Route('/{slug}', name: 'shop_page_root_redirect', requirements: ['slug' => ShopRoutes::PAGE_SLUG_REQUIREMENTS])]
    public function redirectPage(string $slug): RedirectResponse
    {
        return $this->redirectToRoute('shop_page', ['_locale' => $this->defaultLocale, 'slug' => $slug]);
    }
}
