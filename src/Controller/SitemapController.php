<?php

namespace App\Controller;

use App\Service\Seo\SitemapService;
use App\Service\Seo\SitemapXmlRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'shop_sitemap', methods: ['GET'])]
    public function index(
        Request $request,
        SitemapService $sitemapService,
        SitemapXmlRenderer $sitemapXmlRenderer,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $entries = $sitemapService->buildEntries($request->getHost());

        if ($entries === []) {
            throw new NotFoundHttpException();
        }

        $content = $sitemapXmlRenderer->render(
            $entries,
            $urlGenerator->generate('shop_sitemap_stylesheet', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    #[Route('/sitemap.xsl', name: 'shop_sitemap_stylesheet', methods: ['GET'])]
    public function stylesheet(): Response
    {
        $path = $this->getParameter('kernel.project_dir').'/public/sitemap.xsl';

        return new Response((string) file_get_contents($path), Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
