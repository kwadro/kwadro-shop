<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\InviteActivateFormType;
use App\Routing\ShopRoutes;
use App\Service\User\UserInviteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class InviteActivateController extends AbstractController
{
    public function __construct(
        private readonly UserInviteService $userInviteService,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    #[Route(
        '/{_locale}/invite/{token}',
        name: 'app_invite_activate',
        requirements: [
            '_locale' => ShopRoutes::LOCALE_REQUIREMENTS,
            'token' => '.+',
        ],
        methods: ['GET', 'POST'],
    )]
    public function __invoke(string $_locale, string $token, Request $request): Response
    {
        $current = $this->getUser();
        if ($current instanceof User) {
            return $this->redirectToRoute('shop_home', ['_locale' => $_locale]);
        }

        $user = $this->userInviteService->findUserByInviteToken($token);
        if (!$user instanceof User) {
            $this->addFlash('danger', $this->translator->trans('auth.invite_invalid'));

            return $this->redirectToRoute('app_login', ['_locale' => $_locale]);
        }

        $form = $this->createForm(InviteActivateFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $this->userInviteService->activate($user, $plainPassword);
            $this->security->login($user, firewallName: 'main');
            $this->addFlash('success', $this->translator->trans('auth.invite_activated'));

            return $this->redirectToRoute('shop_home', ['_locale' => $_locale]);
        }

        return $this->render('registration/invite_activate.html.twig', [
            'form' => $form,
            'userEmail' => $user->getEmail(),
        ]);
    }
}
