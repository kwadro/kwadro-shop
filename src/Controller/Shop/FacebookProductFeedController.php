<?php

namespace App\Controller\Shop;

use App\Entity\ProductFeedSetting;
use App\Repository\ProductFeedSettingRepository;
use App\Service\Feed\FacebookProductFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publishes the Facebook Catalog product feed for categories with "Publish to feed" = Yes.
 * Only in-stock products are included.
 */
final class FacebookProductFeedController extends AbstractController
{
    public function __construct(
        private readonly ProductFeedSettingRepository $settings,
        private readonly FacebookProductFeedBuilder $feedBuilder,
    ) {
    }

    #[Route(
        '/feed/facebook/products.{_format}',
        name: 'shop_facebook_product_feed',
        requirements: ['_format' => 'csv|tsv|txt'],
        defaults: ['_format' => 'csv'],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $_format): Response
    {
        $setting = $this->settings->findFirstEnabled();
        if (!$setting instanceof ProductFeedSetting) {
            throw new NotFoundHttpException('Product feed is not configured.');
        }

        $expected = $setting->getAccessToken();
        if ($expected !== null && $expected !== '') {
            $provided = (string) $request->query->get('token', '');
            if (!hash_equals($expected, $provided)) {
                throw new AccessDeniedHttpException('Invalid product feed token.');
            }
        }

        $format = ($_format === 'tsv' || $_format === 'txt')
            ? ProductFeedSetting::FORMAT_TSV
            : ProductFeedSetting::FORMAT_CSV;

        $body = $this->feedBuilder->build($setting, $format);
        $contentType = $format === ProductFeedSetting::FORMAT_TSV
            ? 'text/tab-separated-values; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="facebook-products.'.$format.'"',
            'Cache-Control' => 'public, max-age=900',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
