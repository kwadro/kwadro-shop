<?php

namespace App\Controller\Admin;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use App\Repository\MailboxAccountRepository;
use App\Repository\MailboxMessageRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\Mail\Mailbox\MailboxSmtpSendService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Custom admin page — must use #[AdminRoute] so EasyAdmin builds AdminContext (ea).
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
#[AdminRoute('/mailbox', name: 'mailbox')]
class MailboxComposeController extends AbstractController
{
    public function __construct(
        private readonly MailboxAccountRepository $accountRepository,
        private readonly MailboxMessageRepository $messageRepository,
        private readonly OrderRepository $orderRepository,
        private readonly UserRepository $userRepository,
        private readonly MailboxSmtpSendService $smtpSendService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[AdminRoute('/compose', name: 'compose', options: ['methods' => ['GET', 'POST']])]
    public function compose(Request $request): Response
    {
        $accounts = $this->accountRepository->findActive();
        if ($accounts === []) {
            $this->addFlash('warning', $this->translator->trans('admin.mailbox_compose.no_accounts', [], 'messages'));

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(MailboxAccountCrudController::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        $replyTo = trim((string) $request->query->get('to', ''));
        $replySubject = trim((string) $request->query->get('subject', ''));
        $mailboxId = (int) $request->query->get('mailbox', 0);
        $parentId = (int) $request->query->get('parent', 0);
        $returnOrderId = (int) $request->query->get('return_order', 0);
        $returnAction = $this->normalizeReturnAction((string) $request->query->get('return_action', Action::EDIT));
        $linkType = trim((string) $request->query->get('link_type', ''));
        $linkRef = trim((string) $request->query->get('link_ref', ''));
        $parent = $parentId > 0 ? $this->messageRepository->find($parentId) : null;
        if ($parent instanceof MailboxMessage) {
            if ($replyTo === '') {
                $replyTo = $parent->getReplyToAddress();
            }
            if ($replySubject === '') {
                $replySubject = $parent->getSubject();
            }
            if ($mailboxId === 0 && $parent->getMailbox() !== null) {
                $mailboxId = (int) $parent->getMailbox()->getId();
            }
            if ($linkType === '') {
                $linkType = (string) ($parent->getLinkType() ?? '');
            }
            if ($linkRef === '') {
                $linkRef = (string) ($parent->getLinkRef() ?? '');
            }
        } else {
            $parent = null;
        }

        $selectedAccount = null;
        if ($mailboxId > 0) {
            foreach ($accounts as $account) {
                if ($account->getId() === $mailboxId) {
                    $selectedAccount = $account;
                    break;
                }
            }
        }
        $selectedAccount ??= $accounts[0];

        $subject = $replySubject;
        if ($parent instanceof MailboxMessage && $subject !== '' && !str_starts_with(mb_strtolower($subject), 're:')) {
            $subject = 'Re: '.$subject;
        }

        $form = [
            'mailbox_id' => $selectedAccount->getId(),
            'to' => $replyTo,
            'cc' => '',
            'bcc' => '',
            'subject' => $subject,
            'body' => '',
            'parent_id' => $parent?->getId() ?? 0,
            'link_type' => $linkType,
            'link_ref' => $linkRef,
            'return_order' => $returnOrderId,
            'return_action' => $returnAction,
            'link_locked' => $returnOrderId > 0 && $linkType === MailboxMessage::LINK_ORDER && $linkRef !== '',
        ];
        $error = null;
        $cancelUrl = $this->resolveOrderReturnUrl($returnOrderId, $returnAction)
            ?? $this->adminUrlGenerator
                ->unsetAllExcept(EA::DASHBOARD_CONTROLLER_FQCN)
                ->setController(MailboxMessageCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl();

        if ($request->isMethod('POST')) {
            $form['mailbox_id'] = (int) $request->request->get('mailbox_id', 0);
            $form['to'] = trim((string) $request->request->get('to', ''));
            $form['cc'] = trim((string) $request->request->get('cc', ''));
            $form['bcc'] = trim((string) $request->request->get('bcc', ''));
            $form['subject'] = trim((string) $request->request->get('subject', ''));
            $form['body'] = (string) $request->request->get('body', '');
            $form['parent_id'] = (int) $request->request->get('parent_id', 0);
            $form['return_order'] = (int) $request->request->get('return_order', 0);
            $form['return_action'] = $this->normalizeReturnAction((string) $request->request->get('return_action', Action::EDIT));
            $returnOrderId = $form['return_order'];
            $returnAction = $form['return_action'];
            $cancelUrl = $this->resolveOrderReturnUrl($returnOrderId, $returnAction)
                ?? $cancelUrl;
            $form['link_type'] = trim((string) $request->request->get('link_type', ''));
            $form['link_ref'] = trim((string) $request->request->get('link_ref', ''));
            $form['link_locked'] = $request->request->getBoolean('link_locked');
            $parent = $form['parent_id'] > 0 ? $this->messageRepository->find($form['parent_id']) : null;
            if (!$parent instanceof MailboxMessage) {
                $parent = null;
            }

            $account = $this->accountRepository->find($form['mailbox_id']);
            if (!$account instanceof MailboxAccount || !$account->isActive()) {
                $error = $this->translator->trans('admin.mailbox_compose.invalid_account', [], 'messages');
            } elseif ($form['to'] === '') {
                $error = $this->translator->trans('admin.mailbox_compose.to_required', [], 'messages');
            } elseif ($form['subject'] === '') {
                $error = $this->translator->trans('admin.mailbox_compose.subject_required', [], 'messages');
            } elseif (trim(strip_tags($form['body'])) === '') {
                $error = $this->translator->trans('admin.mailbox_compose.body_required', [], 'messages');
            } else {
                $link = $this->resolveLink($form['link_type'], $form['link_ref']);
                if ($link['error'] !== null) {
                    $error = $link['error'];
                } else {
                    try {
                        $cc = $this->splitAddresses($form['cc']);
                        $messageId = $this->smtpSendService->send(
                            $account,
                            $form['to'],
                            $form['subject'],
                            $form['body'],
                            false,
                            $cc,
                            $this->splitAddresses($form['bcc']),
                            $parent?->getMessageId(),
                        );
                        $this->storeSentMessage(
                            $account,
                            $parent,
                            $messageId,
                            $form['to'],
                            $cc,
                            $form['subject'],
                            $form['body'],
                            $link['type'],
                            $link['ref'],
                        );
                        $this->addFlash('success', $this->translator->trans('admin.mailbox_compose.sent', [], 'messages'));

                        $successUrl = $this->resolveOrderReturnUrl($returnOrderId, $returnAction)
                            ?? $this->adminUrlGenerator
                                ->unsetAllExcept(EA::DASHBOARD_CONTROLLER_FQCN)
                                ->setController(MailboxSentMessageCrudController::class)
                                ->setAction(Action::INDEX)
                                ->generateUrl();

                        return $this->redirect($successUrl);
                    } catch (\Throwable $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }

        return $this->render('admin/mailbox/compose.html.twig', [
            'accounts' => $accounts,
            'form' => $form,
            'error' => $error,
            'inbox_url' => $cancelUrl,
        ]);
    }

    private function normalizeReturnAction(string $action): string
    {
        return \in_array($action, [Action::EDIT, Action::DETAIL], true) ? $action : Action::EDIT;
    }

    private function resolveOrderReturnUrl(int $returnOrderId, string $returnAction): ?string
    {
        if ($returnOrderId <= 0) {
            return null;
        }

        return $this->adminUrlGenerator
            ->unsetAllExcept(EA::DASHBOARD_CONTROLLER_FQCN)
            ->setController(OrderCrudController::class)
            ->setAction($this->normalizeReturnAction($returnAction))
            ->setEntityId($returnOrderId)
            ->generateUrl();
    }

    /**
     * @param list<string> $cc
     */
    private function storeSentMessage(
        MailboxAccount $account,
        ?MailboxMessage $parent,
        string $messageId,
        string $to,
        array $cc,
        string $subject,
        string $body,
        ?string $linkType,
        ?string $linkRef,
    ): void {
        $recipients = array_values(array_unique(array_merge($this->splitAddresses($to), $cc)));
        $preview = trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '');

        $message = (new MailboxMessage())
            ->setMailbox($account)
            ->setRemoteUid('sent-'.bin2hex(random_bytes(12)))
            ->setMessageId($messageId)
            ->setFromAddress($account->getEmail())
            ->setFromName($account->getName())
            ->setToAddresses($recipients !== [] ? implode(', ', $recipients) : null)
            ->setSubject($subject)
            ->setBodyText($body)
            ->setBodyPreview($preview !== '' ? $preview : null)
            ->setReceivedAt(new \DateTimeImmutable())
            ->setIsSeen(true)
            ->setIsNotified(true)
            ->setFolder(MailboxMessage::FOLDER_SENT)
            ->setMessageGroup($parent?->getMessageGroup() ?? MailboxAccount::DEFAULT_MESSAGE_GROUP)
            ->setParent($parent)
            ->setLinkType($linkType)
            ->setLinkRef($linkRef);

        if ($parent instanceof MailboxMessage) {
            $this->messageRepository->shareConversation($message, $parent);
            $this->messageRepository->inheritLinkIfEmpty($message, $parent);
        } else {
            $this->messageRepository->continueOrStartConversation($message);
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();
    }

    /**
     * @return array{type: ?string, ref: ?string, error: ?string}
     */
    private function resolveLink(string $type, string $ref): array
    {
        if ($type === '' && $ref === '') {
            return ['type' => null, 'ref' => null, 'error' => null];
        }

        if ($type === '' || $ref === '') {
            return [
                'type' => null,
                'ref' => null,
                'error' => $this->translator->trans('admin.mailbox_compose.link_incomplete', [], 'messages'),
            ];
        }

        if ($type === MailboxMessage::LINK_ORDER) {
            if ($this->orderRepository->findOneByOrderNumber($ref) === null) {
                return [
                    'type' => null,
                    'ref' => null,
                    'error' => $this->translator->trans('admin.mailbox_compose.link_order_not_found', ['%ref%' => $ref], 'messages'),
                ];
            }

            return ['type' => MailboxMessage::LINK_ORDER, 'ref' => $ref, 'error' => null];
        }

        if ($type === MailboxMessage::LINK_USER) {
            if (!ctype_digit($ref) || $this->userRepository->find((int) $ref) === null) {
                return [
                    'type' => null,
                    'ref' => null,
                    'error' => $this->translator->trans('admin.mailbox_compose.link_user_not_found', ['%ref%' => $ref], 'messages'),
                ];
            }

            return ['type' => MailboxMessage::LINK_USER, 'ref' => $ref, 'error' => null];
        }

        return [
            'type' => null,
            'ref' => null,
            'error' => $this->translator->trans('admin.mailbox_compose.link_incomplete', [], 'messages'),
        ];
    }

    /** @return list<string> */
    private function splitAddresses(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/u', $raw) ?: [];
        $emails = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return $emails;
    }
}
