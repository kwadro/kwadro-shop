<?php

namespace App\Controller\Shop;

use App\Repository\LocaleRepository;
use App\Repository\MegaMenuTranslationRepository;
use App\Repository\SiteRepository;
use App\Routing\ShopRoutes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    #[Route(
        '/{_locale}/{slug}',
        name: 'shop_page',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'slug' => ShopRoutes::PAGE_SLUG_REQUIREMENTS,
        ],
    )]
    public function show(
        string $_locale,
        string $slug,
        Request $request,
        SiteRepository $siteRepo,
        LocaleRepository $localeRepo,
        MegaMenuTranslationRepository $menuTranslationRepo,
    ): Response {
        $site = $siteRepo->findOneBy(['domain' => $request->getHost()]);
        $locale = $localeRepo->findOneBy(['code' => $_locale]);

        if (!$site || !$locale) {
            throw new NotFoundHttpException();
        }

        $page = $menuTranslationRepo->findOnePublishedBySiteLocaleAndSlug($site, $locale, $slug);

        if (!$page) {
            throw new NotFoundHttpException();
        }

        return $this->render('shop/page/show.html.twig', [
            'page' => $page,
        ]);
    }
}
