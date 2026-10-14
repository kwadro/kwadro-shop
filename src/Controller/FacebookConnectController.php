<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FacebookConnectController extends AbstractController
{
    #[Route('/connect/facebook', name: 'connect_facebook_start')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('facebook')
            ->redirect(['public_profile', 'email'], []);
    }

    /**
     * Facebook redirects here; FacebookAuthenticator handles the request.
     */
    #[Route('/connect/facebook/check', name: 'connect_facebook_check')]
    public function check(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
