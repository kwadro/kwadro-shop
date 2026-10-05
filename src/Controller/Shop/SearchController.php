<?php

namespace App\Controller\Shop;

use App\Routing\ShopRoutes;
use App\Service\ProductListingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly ProductListingService $productListing,
    ) {
    }

    #[Route(
        '/{_locale}/search',
        name: 'shop_search',
        requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS],
        methods: ['GET'],
    )]
    public function search(string $_locale, Request $request): Response
    {
        $listing = $this->productListing->list([], $request, true);

        return $this->render('shop/search/index.html.twig', $listing);
    }
}
