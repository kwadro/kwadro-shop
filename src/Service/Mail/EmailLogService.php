<?php

namespace App\Service\Mail;

use App\Entity\EmailLog;
use App\Entity\EmailLogStatus;
use App\Entity\Order;
use App\Entity\OrderEmail;
use Doctrine\ORM\EntityManagerInterface;

final class EmailLogService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function logSkipped(
        string $eventCode,
        string $recipient,
        string $reason,
        ?Order $order = null,
        ?OrderEmail $orderEmail = null,
        array $context = [],
    ): EmailLog {
        $log = $this->createBaseLog($eventCode, $recipient, $order, $orderEmail, $context)
            ->setStatus(EmailLogStatus::Skipped)
            ->setSkipReason($reason);

        return $this->persist($log);
    }

    /** @param array<string, mixed> $context */
    public function logSent(
        string $eventCode,
        string $recipient,
        string $senderEmail,
        string $senderName,
        string $subject,
        string $body,
        bool $isHtml,
        ?Order $order = null,
        ?OrderEmail $orderEmail = null,
        array $context = [],
    ): EmailLog {
        $log = $this->createBaseLog($eventCode, $recipient, $order, $orderEmail, $context)
            ->setStatus(EmailLogStatus::Sent)
            ->setSenderEmail($senderEmail)
            ->setSenderName($senderName)
            ->setSubject($subject)
            ->setBody($body)
            ->setIsHtml($isHtml);

        return $this->persist($log);
    }

    /** @param array<string, mixed> $context */
    public function logFailed(
        string $eventCode,
        string $recipient,
        string $senderEmail,
        string $senderName,
        string $subject,
        string $body,
        bool $isHtml,
        string $errorMessage,
        ?Order $order = null,
        ?OrderEmail $orderEmail = null,
        array $context = [],
    ): EmailLog {
        $log = $this->createBaseLog($eventCode, $recipient, $order, $orderEmail, $context)
            ->setStatus(EmailLogStatus::Failed)
            ->setSenderEmail($senderEmail)
            ->setSenderName($senderName)
            ->setSubject($subject)
            ->setBody($body)
            ->setIsHtml($isHtml)
            ->setErrorMessage($errorMessage);

        return $this->persist($log);
    }

    /** @param array<string, mixed> $context */
    private function createBaseLog(
        string $eventCode,
        string $recipient,
        ?Order $order,
        ?OrderEmail $orderEmail,
        array $context,
    ): EmailLog {
        $log = (new EmailLog())
            ->setEventCode($eventCode)
            ->setRecipientEmail($recipient)
            ->setOrder($order)
            ->setOrderEmail($orderEmail)
            ->setContext($context !== [] ? $context : null);

        if ($order !== null) {
            $log->setOrderNumber($order->getOrderNumber());
        }

        return $log;
    }

    private function persist(EmailLog $log): EmailLog
    {
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }
}
