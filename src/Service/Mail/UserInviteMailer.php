<?php

namespace App\Service\Mail;

use App\Entity\OrderEmailEvent;
use App\Entity\User;
use App\Repository\EmailSenderRepository;
use App\Repository\OrderEmailRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class UserInviteMailer
{
    public function __construct(
        private readonly OrderEmailMailer $orderEmailMailer,
        private readonly OrderEmailRepository $orderEmailRepository,
        private readonly EmailSenderRepository $emailSenderRepository,
        private readonly UserEmailContextFactory $userEmailContextFactory,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(User $user, string $inviteUrl, string $locale = 'ua'): void
    {
        $extra = [
            'invite_url' => $inviteUrl,
            'activation_url' => $inviteUrl,
        ];

        $configured = $this->orderEmailRepository->findOneByCode(OrderEmailEvent::InviteUser->value);
        if ($configured !== null && $configured->getTemplate() !== null && $configured->getSender() !== null) {
            $this->orderEmailMailer->sendForUser($user, OrderEmailEvent::InviteUser, $extra);

            return;
        }

        $this->sendFallback($user, $inviteUrl, $locale);
    }

    private function sendFallback(User $user, string $inviteUrl, string $locale): void
    {
        $recipient = trim((string) ($user->getEmail() ?? ''));
        if ($recipient === '') {
            $this->logger->warning('Invite email skipped: recipient empty.');

            return;
        }

        $sender = $this->emailSenderRepository->findOneBy([], ['id' => 'ASC']);
        if ($sender === null) {
            $this->logger->warning('Invite email skipped: no email sender configured.');

            return;
        }

        $context = array_merge(
            $this->userEmailContextFactory->create($user),
            [
                'invite_url' => $inviteUrl,
                'locale' => $locale,
            ],
        );

        $subject = $this->translator->trans('auth.invite_email_subject', [], 'messages', $locale);
        $html = $this->twig->render('emails/invite_user.html.twig', $context);

        $message = (new Email())
            ->from(new Address($sender->getEmail(), $sender->getName()))
            ->to($recipient)
            ->subject($subject)
            ->html($html);

        try {
            $this->mailer->send($message);
            $this->logger->info('Invite email sent via fallback template.', [
                'user_id' => $user->getId(),
                'recipient' => $recipient,
            ]);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Invite email failed.', [
                'user_id' => $user->getId(),
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
