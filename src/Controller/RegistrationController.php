<?php

namespace App\Controller;

use App\Entity\OrderEmailEvent;
use App\Entity\User;
use App\Form\Type\RegistrationFormType;
use App\Routing\ShopRoutes;
use App\Service\Mail\OrderEmailMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly OrderEmailMailer $orderEmailMailer,
    ) {
    }

    #[Route('/{_locale}/register', name: 'app_register', requirements: ['_locale' => ShopRoutes::LOCALE_REQUIREMENTS])]
    public function register(
        string $_locale,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('admin', ['_locale' => 'uk']);
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_login', ['_locale' => $_locale]);
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setRoles([User::ROLE_USER]);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->orderEmailMailer->sendForUser($user, OrderEmailEvent::RegisterUser);

            $this->addFlash('success', $this->translator->trans('auth.registration_success'));

            return $this->redirectToRoute('app_login', ['_locale' => $_locale]);
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
