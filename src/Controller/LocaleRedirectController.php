<?php

namespace App\Controller;

use App\Routing\ShopRoutes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocaleRedirectController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    #[Route(
        '/ua',
        name: 'shop_default_locale_home_redirect',
        priority: 40,
    )]
    #[Route(
        '/ua/',
        name: 'shop_default_locale_home_redirect_slash',
        priority: 40,
    )]
    public function redirectDefaultLocaleHome(Request $request): RedirectResponse
    {
        $target = '/';
        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return $this->redirect($target, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/'.ShopRoutes::LEGACY_LOCALE_PREFIX.'/{path}',
        name: 'shop_legacy_uk_locale_redirect',
        requirements: ['path' => '.*'],
        priority: 40,
    )]
    public function redirectLegacyUkPrefix(string $path, Request $request): RedirectResponse
    {
        return $this->redirectLegacyUkPath($path, $request->getQueryString());
    }

    #[Route(
        '/'.ShopRoutes::LEGACY_LOCALE_PREFIX,
        name: 'shop_legacy_uk_locale_redirect_root',
        priority: 40,
    )]
    public function redirectLegacyUkRoot(Request $request): RedirectResponse
    {
        return $this->redirectLegacyUkPath('', $request->getQueryString());
    }

    #[Route(
        '/admin/'.ShopRoutes::LEGACY_LOCALE_PREFIX.'/{path}',
        name: 'admin_legacy_uk_locale_redirect',
        requirements: ['path' => '.*'],
        priority: 40,
    )]
    public function redirectLegacyAdminUkPrefix(string $path, Request $request): RedirectResponse
    {
        $suffix = trim($path, '/');
        $target = '/admin/ua'.($suffix !== '' ? '/'.$suffix : '');
        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return $this->redirect($target, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/admin/'.ShopRoutes::LEGACY_LOCALE_PREFIX,
        name: 'admin_legacy_uk_locale_redirect_root',
        priority: 40,
    )]
    public function redirectLegacyAdminUkRoot(Request $request): RedirectResponse
    {
        $target = '/admin/ua';
        $query = $request->getQueryString();
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return $this->redirect($target, Response::HTTP_MOVED_PERMANENTLY);
    }

    private function redirectLegacyUkPath(string $path, ?string $query): RedirectResponse
    {
        $suffix = trim($path, '/');
        // Default-locale home is unprefixed (/); other pages keep /ua/...
        $target = $suffix === '' ? '/' : '/ua/'.$suffix;
        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return $this->redirect($target, Response::HTTP_MOVED_PERMANENTLY);
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
            'Disallow: /ua/admser',
            'Disallow: /en/admser',
            'Disallow: /checkout',
            'Disallow: /ua/checkout',
            'Disallow: /en/checkout',
            '',
            'Sitemap: '.$urlGenerator->generate('shop_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '',
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    #[Route('/{slug}', name: 'shop_page_root_redirect', requirements: ['slug' => ShopRoutes::PAGE_SLUG_REQUIREMENTS], priority: -10)]
    public function redirectPage(string $slug): RedirectResponse
    {
        // If a locale code somehow matches /{slug} (e.g. trailing-slash edge case), go to home.
        if (preg_match('#^(?:'.ShopRoutes::LOCALE_REQUIREMENTS.')$#', $slug) === 1) {
            return $this->redirectToRoute('shop_home', ['_locale' => $slug], Response::HTTP_MOVED_PERMANENTLY);
        }

        if (preg_match('#^(?:'.ShopRoutes::RESERVED_PAGE_SLUGS.')$#', $slug) === 1) {
            throw new NotFoundHttpException();
        }

        return $this->redirectToRoute('shop_page', ['_locale' => $this->defaultLocale, 'slug' => $slug]);
    }
}
