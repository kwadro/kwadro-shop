<?php

namespace App\Security;

use App\Entity\OrderEmailEvent;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Mail\OrderEmailMailer;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\FacebookUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

class FacebookAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OrderEmailMailer $orderEmailMailer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_facebook_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('facebook');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
            /** @var FacebookUser $facebookUser */
            $facebookUser = $client->fetchUserFromToken($accessToken);

            $facebookId = $facebookUser->getId();
            if ($facebookId === null || $facebookId === '') {
                throw new CustomUserMessageAuthenticationException('auth.facebook_failed');
            }

            $user = $this->userRepository->findOneBy(['facebookId' => $facebookId]);
            if ($user instanceof User) {
                return $user;
            }

            $email = $facebookUser->getEmail();
            if ($email === null || $email === '') {
                throw new CustomUserMessageAuthenticationException('auth.facebook_email_required');
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);
            if ($user instanceof User) {
                $user->setFacebookId($facebookId);
                if ($user->getFullName() === null && $facebookUser->getName()) {
                    $user->setFullName($facebookUser->getName());
                }
                $this->entityManager->flush();

                return $user;
            }

            $user = new User();
            $user->setEmail($email);
            $user->setFacebookId($facebookId);
            $user->setFullName($facebookUser->getName());
            $user->setRoles([User::ROLE_USER]);
            $user->setPassword($this->passwordHasher->hashPassword(
                $user,
                bin2hex(random_bytes(32))
            ));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->orderEmailMailer->sendForUser($user, OrderEmailEvent::RegisterUser);

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $locale = $request->getSession()->get('_locale', 'uk');
        if (!\is_string($locale) || $locale === '') {
            $locale = 'uk';
        }

        return new RedirectResponse($this->urlGenerator->generate('shop_home', ['_locale' => $locale]));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $locale = $request->getSession()->get('_locale', 'uk');
        if (!\is_string($locale) || $locale === '') {
            $locale = 'uk';
        }

        $messageKey = $exception->getMessageKey();
        $message = str_starts_with($messageKey, 'auth.')
            ? $this->translator->trans($messageKey, $exception->getMessageData(), 'messages')
            : $this->translator->trans('auth.facebook_failed', [], 'messages');

        $request->getSession()->getFlashBag()->add('error', $message);

        return new RedirectResponse($this->urlGenerator->generate('app_login', ['_locale' => $locale]));
    }
}
