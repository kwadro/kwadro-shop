<?php

namespace App\Service\Mail;

use App\Entity\EmailLog;
use App\Entity\EmailSender;
use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateContext;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class EmailTemplateTestService
{
    private const EVENT_CODE = 'admin_template_test';

    public function __construct(
        private readonly OrderEmailContextFactory $orderContextFactory,
        private readonly UserEmailContextFactory $userContextFactory,
        private readonly EmailBrandingContextProvider $brandingContextProvider,
        private readonly OrderEmailTemplateRenderer $templateRenderer,
        private readonly EmailLogService $emailLogService,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{subject: string, body: string, isHtml: bool}
     */
    public function preview(EmailTemplate $template, array $context, ?Order $order = null): array
    {
        return $this->templateRenderer->render(
            $template,
            $this->mergeBrandingContext($template, $context, $order),
        );
    }

    /**
     * @return array{log: EmailLog, sent: bool}
     */
    public function send(
        EmailTemplate $template,
        EmailSender $sender,
        string $recipient,
        array $context,
        ?Order $order = null,
    ): array {
        $rendered = $this->preview($template, $context, $order);
        $senderEmail = $sender->getEmail();
        $senderName = $sender->getName();
        $storedContext = array_merge($context, [
            'template_id' => $template->getId(),
            'sender_id' => $sender->getId(),
            'test_mode' => true,
        ]);

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
                self::EVENT_CODE,
                $recipient,
                $senderEmail,
                $senderName,
                $rendered['subject'],
                $rendered['body'],
                $rendered['isHtml'],
                $order,
                null,
                $storedContext,
            );
            $this->logger->info('Admin template test email sent.', [
                'recipient' => $recipient,
                'template_id' => $template->getId(),
                'email_log_id' => $log->getId(),
            ]);

            return ['log' => $log, 'sent' => true];
        } catch (TransportExceptionInterface $exception) {
            $log = $this->emailLogService->logFailed(
                self::EVENT_CODE,
                $recipient,
                $senderEmail,
                $senderName,
                $rendered['subject'],
                $rendered['body'],
                $rendered['isHtml'],
                $exception->getMessage(),
                $order,
                null,
                $storedContext,
            );
            $this->logger->error('Admin template test email failed.', [
                'recipient' => $recipient,
                'template_id' => $template->getId(),
                'email_log_id' => $log->getId(),
                'error' => $exception->getMessage(),
            ]);

            return ['log' => $log, 'sent' => false];
        }
    }

    /** @return array<string, string> */
    public function buildContext(EmailTemplate $template, ?Order $order, ?User $user, string $recipient): array
    {
        $recipient = trim($recipient);

        if ($template->getContext() === EmailTemplateContext::User) {
            if ($user !== null) {
                $context = $this->userContextFactory->create($user);
                if ($recipient !== '') {
                    $context['user_email'] = $recipient;
                }

                return $context;
            }

            return $this->sampleUserContext($recipient);
        }

        if ($order !== null) {
            $payment = $this->resolvePayment($order);
            $context = $this->orderContextFactory->create($order, $payment);
            if ($recipient !== '') {
                $context['customer_email'] = $recipient;
            }

            return $this->mergeUserContext($context, $order->getCustomer());
        }

        return $this->sampleOrderContext($recipient);
    }

    /** @param array<string, string> $context */
    private function mergeBrandingContext(EmailTemplate $template, array $context, ?Order $order = null): array
    {
        $locale = $order?->getLocale() ?? 'uk';

        return array_merge(
            $this->brandingContextProvider->create($template->getSite(), $locale),
            $context,
        );
    }

    private function resolvePayment(Order $order): ?Payment
    {
        $payment = $order->getPayments()->first();

        return $payment instanceof Payment ? $payment : null;
    }

    /** @param array<string, string> $context */
    private function mergeUserContext(array $context, ?User $user): array
    {
        if ($user === null) {
            return $context;
        }

        return array_merge($context, $this->userContextFactory->create($user));
    }

    /** @return array<string, string> */
    private function sampleUserContext(string $recipient): array
    {
        $email = trim($recipient) !== '' ? trim($recipient) : 'test@example.com';

        return [
            'user_id' => '0',
            'user_email' => $email,
            'user_name' => 'Тестовий Користувач',
            'user_phone' => '+380501234567',
        ];
    }

    /** @return array<string, string> */
    private function sampleOrderContext(string $recipient): array
    {
        $email = trim($recipient) !== '' ? trim($recipient) : 'test@example.com';

        return [
            'order_id' => '0',
            'order_number' => 'TEST-0001',
            'customer_name' => 'Тестовий Клієнт',
            'customer_email' => $email,
            'customer_phone' => '+380501234567',
            'order_amount' => '1 500,00',
            'amount' => '1 500,00',
            'order_created_at' => '17.09.2026, 19:50',
            'created_at' => '17.09.2026, 19:50',
            'shipping_cost' => '80,00',
            'order_total' => '1 580,00',
            'currency' => 'UAH',
            'order_status' => 'paid',
            'payment_method' => 'mono_card',
            'items_summary' => 'Товар A × 1, Товар B × 2',
            'order_items_table' => $this->sampleOrderItemsTable(),
            'delivery_summary' => 'Київ, Відділення №1',
            'shipment_pay_url' => 'https://example.test/uk/order/TEST-0001/shipment-pay?token=test',
            'shipment_pay_iban_url' => 'https://example.test/uk/order/TEST-0001/shipment-pay/iban?token=test',
            'shipment_prepayment_amount' => '100,00',
            'user_id' => '0',
            'user_email' => $email,
            'user_name' => 'Тестовий Користувач',
            'user_phone' => '+380501234567',
        ];
    }

    private function sampleOrderItemsTable(): string
    {
        return <<<'HTML'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;border-bottom:1px solid #eef2f7;"><tr><td style="padding:9px 0;font-family:'Segoe UI',Arial,sans-serif;"><p style="margin:0 0 3px;font-size:13px;line-height:1.45;font-weight:600;color:#1e293b;">ТВ антена DVB-T2 Delta 3000</p><p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;">Delta-3000 · 1 шт × 300,00 ₴ = <strong style="color:#1e293b;">300,00 ₴</strong></p></td></tr></table>
HTML;
    }
}
