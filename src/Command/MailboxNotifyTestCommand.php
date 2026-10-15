<?php

namespace App\Command;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use App\Repository\MailboxAccountRepository;
use App\Repository\MailboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailbox:notify-test',
    description: 'Create (or re-queue) a test mailbox message so the admin toast poll can show a notification',
)]
final class MailboxNotifyTestCommand extends Command
{
    public function __construct(
        private readonly MailboxAccountRepository $accountRepository,
        private readonly MailboxMessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Mailbox account id (default: first active)')
            ->addOption('message-id', null, InputOption::VALUE_REQUIRED, 'Re-queue an existing message id (set is_notified=0, is_seen=0)')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Subject for a new test message', 'Test mailbox notification')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'From address for a new test message', 'notify-test@example.com')
            ->addOption('from-name', null, InputOption::VALUE_REQUIRED, 'From name for a new test message', 'Notify Test')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'How many new test messages to create', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $reuseId = $input->getOption('message-id');
        if ($reuseId !== null) {
            return $this->requeueExisting($io, (int) $reuseId);
        }

        $account = $this->resolveAccount($input->getOption('id'));
        if ($account === null) {
            $io->error('No active mailbox account found. Create/activate one in admin, or pass --id=…');

            return Command::FAILURE;
        }

        $count = max(1, min(20, (int) $input->getOption('count')));
        $subject = (string) $input->getOption('subject');
        $from = (string) $input->getOption('from');
        $fromName = (string) $input->getOption('from-name');
        $createdIds = [];

        for ($i = 1; $i <= $count; ++$i) {
            $suffix = $count > 1 ? sprintf(' (%d/%d)', $i, $count) : '';
            $uid = 'test-notify-'.bin2hex(random_bytes(8));

            $message = (new MailboxMessage())
                ->setMailbox($account)
                ->setRemoteUid($uid)
                ->setMessageId(sprintf('<%s@notify-test.local>', $uid))
                ->setFromAddress($from)
                ->setFromName($fromName)
                ->setToAddresses($account->getEmail())
                ->setSubject($subject.$suffix)
                ->setBodyPreview('CLI test notification — open any admin page and wait for the poll (~30s).')
                ->setBodyText("This is a test message created by app:mailbox:notify-test.\nIt should appear as an admin toast.")
                ->setReceivedAt(new \DateTimeImmutable())
                ->setIsSeen(false)
                ->setIsNotified(false)
                ->setFolder('INBOX')
                ->setMessageGroup(MailboxAccount::DEFAULT_MESSAGE_GROUP);

            $this->entityManager->persist($message);
            $this->entityManager->flush();
            $createdIds[] = (int) $message->getId();
        }

        $io->success(sprintf(
            'Queued %d test notification(s) for mailbox #%d <%s>.',
            \count($createdIds),
            (int) $account->getId(),
            $account->getEmail(),
        ));
        $io->listing(array_map(
            static fn (int $id): string => sprintf('message id=%d (is_notified=0, is_seen=0)', $id),
            $createdIds,
        ));
        $this->printHowToSee($io);

        return Command::SUCCESS;
    }

    private function requeueExisting(SymfonyStyle $io, int $messageId): int
    {
        $message = $this->messageRepository->find($messageId);
        if (!$message instanceof MailboxMessage) {
            $io->error(sprintf('Mailbox message #%d not found.', $messageId));

            return Command::FAILURE;
        }

        $mailbox = $message->getMailbox();
        if ($mailbox === null || !$mailbox->isActive()) {
            $io->error(sprintf(
                'Message #%d belongs to an inactive/missing mailbox — poll ignores inactive accounts.',
                $messageId,
            ));

            return Command::FAILURE;
        }

        $message
            ->setIsNotified(false)
            ->setIsSeen(false)
            ->setReceivedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $io->success(sprintf(
            'Re-queued message #%d for mailbox <%s> (is_notified=0, is_seen=0, received_at=now).',
            $messageId,
            $mailbox->getEmail(),
        ));
        $this->printHowToSee($io);

        return Command::SUCCESS;
    }

    private function resolveAccount(mixed $idOption): ?MailboxAccount
    {
        if ($idOption !== null) {
            $account = $this->accountRepository->find((int) $idOption);

            return $account instanceof MailboxAccount ? $account : null;
        }

        $active = $this->accountRepository->findActive();

        return $active[0] ?? null;
    }

    private function printHowToSee(SymfonyStyle $io): void
    {
        $io->writeln([
            '',
            'How to see it in admin:',
            '  1) Open any EasyAdmin page while logged in as ROLE_SUPER_ADMIN',
            '  2) Wait for poll (immediately on load, then every ~30 seconds)',
            '  3) Expect a toast top-right: "You received a message" / "Ви отримали лист"',
            '  4) Unread badge bottom-right should show unread count',
            '',
            'Endpoint: GET /admin/uk/mailbox/notifications/poll',
            'Tip: hard-refresh the admin page (Ctrl+F5) so JS/CSS load.',
        ]);
    }
}
