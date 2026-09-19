<?php

namespace App\Controller\Shop;

use App\Routing\ShopRoutes;
use App\Service\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ProductCatalog $productCatalog,
    ) {
    }

    #[Route('/{_locale}', name: 'shop_home', requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS])]
    public function index(string $_locale): Response
    {
        return $this->renderHome();
    }

    #[Route('/{_locale}/admser', name: 'shop_admser', requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS])]
    public function admser(string $_locale): Response
    {
        $response = $this->renderHome(showTopbar: true, blockSearchEngines: true);
        $response->headers->set('X-Robots-Tag', self::SEARCH_ENGINE_BLOCK_DIRECTIVE);

        return $response;
    }

    private function renderHome(bool $showTopbar = false, bool $blockSearchEngines = false): Response
    {
        return $this->render('shop/home/index.html.twig', [
            'product' => $this->productCatalog->getFeaturedProduct(),
            'show_topbar' => $showTopbar,
            'block_search_engines' => $blockSearchEngines,
        ]);
    }

    private const SEARCH_ENGINE_BLOCK_DIRECTIVE = 'noindex, nofollow, noarchive, nosnippet';
}
