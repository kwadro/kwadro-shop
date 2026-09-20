<?php

namespace App\Controller\Shop;

use App\Service\Checkout\Monobank\MonobankWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MonobankWebhookController extends AbstractController
{
    public function __construct(
        private readonly MonobankWebhookHandler $webhookHandler,
    ) {
    }

    #[Route('/webhook/monobank', name: 'shop_monobank_webhook', methods: ['POST'])]
    public function webhook(Request $request): Response
    {
        $rawBody = $request->getContent();
        $xSign = $request->headers->get('X-Sign');

        $result = $this->webhookHandler->handle($rawBody, $xSign);

        return match ($result) {
            'invalid_signature' => new Response('Invalid signature', Response::HTTP_FORBIDDEN),
            'invalid_payload' => new Response('Invalid payload', Response::HTTP_BAD_REQUEST),
            default => new Response('OK', Response::HTTP_OK),
        };
    }
}
