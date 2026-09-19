<?php

namespace App\Service\Mail;

use App\Entity\EmailTemplate;
use App\Entity\Order;
use App\Entity\OrderEmailEvent;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\OrderEmailRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class OrderEmailMailer
{
    public function __construct(
        private readonly OrderEmailRepository $orderEmailRepository,
        private readonly OrderEmailContextFactory $contextFactory,
        private readonly UserEmailContextFactory $userContextFactory,
        private readonly OrderEmailTemplateRenderer $templateRenderer,
        private readonly EmailBrandingContextProvider $brandingContextProvider,
        private readonly EmailLogService $emailLogService,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendForOrder(Order $order, OrderEmailEvent $event, ?Payment $payment = null): void
    {
        $context = $this->contextFactory->create($order, $payment);
        $recipient = $context['customer_email'] ?? '';

        $this->sendConfiguredEmail(
            $event->value,
            $recipient,
            $context,
            $order,
            ['order_number' => $order->getOrderNumber()],
        );
    }

    public function sendForUser(User $user, OrderEmailEvent $event): void
    {
        $context = $this->userContextFactory->create($user);
        $recipient = $context['user_email'] ?? '';

        $this->sendConfiguredEmail(
            $event->value,
            $recipient,
            $context,
            null,
            ['user_id' => $user->getId()],
        );
    }

    /**
     * @param array<string, string> $context
     *
     * @return array<string, string>
     */
    private function mergeBrandingContext(EmailTemplate $template, array $context, ?Order $order = null): array
    {
        $locale = $order?->getLocale() ?? 'uk';

        return array_merge(
            $this->brandingContextProvider->create($template->getSite(), $locale),
            $context,
        );
    }

    /**
     * @param array<string, string> $context
     * @param array<string, scalar|null> $logContext
     */
    private function sendConfiguredEmail(
        string $code,
        string $recipient,
        array $context,
        ?Order $order,
        array $logContext,
    ): void {
        $storedContext = array_merge($context, $logContext);
        $orderEmail = $this->orderEmailRepository->findOneByCode($code);

        if ($orderEmail === null) {
            $log = $this->emailLogService->logSkipped(
                $code,
                $recipient,
                'configuration_not_found',
                $order,
                null,
                $storedContext,
            );
            $this->logger->info('Configured email skipped: configuration not found.', [
                'event' => $code,
                'email_log_id' => $log->getId(),
                ...$logContext,
            ]);

            return;
        }

        $template = $orderEmail->getTemplate();
        $sender = $orderEmail->getSender();
        if ($template === null || $sender === null) {
            $log = $this->emailLogService->logSkipped(
                $code,
                $recipient,
                'template_or_sender_missing',
                $order,
                $orderEmail,
                $storedContext,
            );
            $this->logger->warning('Configured email skipped: template or sender is missing.', [
                'event' => $code,
                'email_log_id' => $log->getId(),
                ...$logContext,
            ]);

            return;
        }

        if ($recipient === '') {
            $log = $this->emailLogService->logSkipped(
                $code,
                $recipient,
                'recipient_empty',
                $order,
                $orderEmail,
                $storedContext,
            );
            $this->logger->warning('Configured email skipped: recipient email is empty.', [
                'event' => $code,
                'email_log_id' => $log->getId(),
                ...$logContext,
            ]);

            return;
        }

        $rendered = $this->templateRenderer->render(
            $template,
            $this->mergeBrandingContext($template, $context, $order),
        );
        $senderEmail = $sender->getEmail();
        $senderName = $sender->getName();

        $message = (new Email())
            ->from(new Address($senderEmail, $senderName))
            ->to($recipient)
            ->subject($rendered['subject']);

        if ($rendered['isHtml']) {
            $message->html($rendered['body']);
        } else {
            $message->text($rendered['body']);
        }

        try {
            $this->mailer->send($message);
            $log = $this->emailLogService->logSent(
                $code,
                $recipient,
                $senderEmail,
                $senderName,
                $rendered['subject'],
                $rendered['body'],
                $rendered['isHtml'],
                $order,
                $orderEmail,
                $storedContext,
            );
            $this->logger->info('Configured email sent.', [
                'event' => $code,
                'recipient' => $recipient,
                'email_log_id' => $log->getId(),
                ...$logContext,
            ]);
        } catch (TransportExceptionInterface $exception) {
            $log = $this->emailLogService->logFailed(
                $code,
                $recipient,
                $senderEmail,
                $senderName,
                $rendered['subject'],
                $rendered['body'],
                $rendered['isHtml'],
                $exception->getMessage(),
                $order,
                $orderEmail,
                $storedContext,
            );
            $this->logger->error('Configured email failed to send.', [
                'event' => $code,
                'recipient' => $recipient,
                'email_log_id' => $log->getId(),
                'error' => $exception->getMessage(),
                ...$logContext,
            ]);
        }
    }
}
