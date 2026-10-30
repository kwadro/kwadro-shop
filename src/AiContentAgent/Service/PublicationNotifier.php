<?php

namespace App\AiContentAgent\Service;

use App\AiContentAgent\ContentAgentAction;
use App\AiContentAgent\Entity\PublicationQueueItem;
use App\Entity\AdminShopNotification;
use App\Entity\BlogArticle;
use App\Repository\AdminShopNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PublicationNotifier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminShopNotificationRepository $notifications,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ContentAgentLogger $logger,
        private readonly string $notifyEmail,
        private readonly string $fromEmail,
    ) {
    }

    public function notify(PublicationQueueItem $item): void
    {
        $article = $item->getBlogArticle();
        if (!$article instanceof BlogArticle || $article->getId() === null) {
            throw new \RuntimeException('Published queue item has no article to announce.');
        }

        $locale = $article->getLocale()?->getCode() ?? 'uk';
        $path = $this->urlGenerator->generate('shop_blog_article', [
            '_locale' => $locale,
            'slug' => $article->getSlug(),
        ]);

        if ($this->notifications->existsFor(AdminShopNotification::CHANNEL_CONTENT, (int) $article->getId())) {
            $this->logger->info(ContentAgentAction::NOTIFY_SENT, 'Publication notification already exists.', [
                'queueItemId' => $item->getId(),
                'articleId' => $article->getId(),
            ], $item->getId());

            return;
        }

        $title = $this->translator->trans('admin.shop_notify.content_published_title', [], 'messages');
        $body = $this->translator->trans('admin.shop_notify.content_published_body', [
            '%title%' => $article->getTitle(),
            '%slug%' => $article->getSlug(),
        ], 'messages');

        $notification = (new AdminShopNotification())
            ->setChannel(AdminShopNotification::CHANNEL_CONTENT)
            ->setAction(AdminShopNotification::ACTION_PUBLISHED)
            ->setEntityId((int) $article->getId())
            ->setTitle($title)
            ->setBody($body)
            ->setTargetPath($path)
            ->setIsNotified(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $emailError = $this->sendEmail($article, $path);
        if ($emailError !== null) {
            $this->logger->warning(ContentAgentAction::NOTIFY_FAILED, $emailError, [
                'queueItemId' => $item->getId(),
                'articleId' => $article->getId(),
            ], $item->getId());
        }

        $this->logger->info(ContentAgentAction::NOTIFY_SENT, 'Publication success was announced.', [
            'queueItemId' => $item->getId(),
            'articleId' => $article->getId(),
            'slug' => $article->getSlug(),
            'path' => $path,
            'email' => $emailError === null && trim($this->notifyEmail) !== '',
        ], $item->getId());
    }

    private function sendEmail(BlogArticle $article, string $path): ?string
    {
        $to = trim($this->notifyEmail);
        $from = trim($this->fromEmail);
        if ($to === '') {
            return null;
        }
        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return 'Publication email was skipped because the from or to address is invalid.';
        }

        try {
            $email = (new Email())
                ->from(new Address($from, 'AI Content Agent'))
                ->to(new Address($to))
                ->subject('Article published: '.$article->getTitle())
                ->text("Article published.\n\n".$article->getTitle()."\n".$path."\n");
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            return 'Publication email failed: '.$exception->getMessage();
        }

        return null;
    }
}
