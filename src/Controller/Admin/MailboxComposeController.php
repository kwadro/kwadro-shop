<?php

namespace App\Controller\Admin;

use App\Entity\MailboxAccount;
use App\Repository\MailboxAccountRepository;
use App\Service\Mail\Mailbox\MailboxSmtpSendService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_SUPER_ADMIN')]
class MailboxComposeController extends AbstractController
{
    public function __construct(
        private readonly MailboxAccountRepository $accountRepository,
        private readonly MailboxSmtpSendService $smtpSendService,
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/admin/{_locale}/mailbox/compose', name: 'admin_mailbox_compose', requirements: ['_locale' => 'uk|en'], methods: ['GET', 'POST'])]
    public function compose(Request $request, string $_locale): Response
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

        $form = [
            'mailbox_id' => $selectedAccount->getId(),
            'to' => $replyTo,
            'cc' => '',
            'bcc' => '',
            'subject' => $replySubject !== '' && !str_starts_with(mb_strtolower($replySubject), 're:')
                ? 'Re: '.$replySubject
                : $replySubject,
            'body' => '',
        ];
        $error = null;

        if ($request->isMethod('POST')) {
            $form['mailbox_id'] = (int) $request->request->get('mailbox_id', 0);
            $form['to'] = trim((string) $request->request->get('to', ''));
            $form['cc'] = trim((string) $request->request->get('cc', ''));
            $form['bcc'] = trim((string) $request->request->get('bcc', ''));
            $form['subject'] = trim((string) $request->request->get('subject', ''));
            $form['body'] = (string) $request->request->get('body', '');

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
                try {
                    $this->smtpSendService->send(
                        $account,
                        $form['to'],
                        $form['subject'],
                        $form['body'],
                        false,
                        $this->splitAddresses($form['cc']),
                        $this->splitAddresses($form['bcc']),
                    );
                    $this->addFlash('success', $this->translator->trans('admin.mailbox_compose.sent', [], 'messages'));

                    return $this->redirect(
                        $this->adminUrlGenerator
                            ->setController(MailboxMessageCrudController::class)
                            ->setAction(Action::INDEX)
                            ->generateUrl()
                    );
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('admin/mailbox/compose.html.twig', [
            'accounts' => $accounts,
            'form' => $form,
            'error' => $error,
            'inbox_url' => $this->adminUrlGenerator
                ->setController(MailboxMessageCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
        ]);
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
