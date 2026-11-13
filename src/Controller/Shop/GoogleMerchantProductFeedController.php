<?php

namespace App\Controller\Shop;

use App\Entity\ProductFeedSetting;
use App\Repository\ProductFeedSettingRepository;
use App\Service\Feed\GoogleMerchantProductFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Publishes the Google Merchant Center product feed (RSS XML).
 * Same product selection as the Facebook feed: in-stock items from categories with Publish to feed = Yes.
 */
final class GoogleMerchantProductFeedController extends AbstractController
{
    public function __construct(
        private readonly ProductFeedSettingRepository $settings,
        private readonly GoogleMerchantProductFeedBuilder $feedBuilder,
    ) {
    }

    #[Route(
        '/google-merchant.xml',
        name: 'shop_google_merchant_product_feed',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): Response
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

        $body = $this->feedBuilder->build($setting);

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
