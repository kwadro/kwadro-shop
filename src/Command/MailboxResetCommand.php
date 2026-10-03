<?php

namespace App\Command;

use App\Entity\MailboxAccount;
use App\Entity\MailboxMessage;
use App\Repository\MailboxAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailbox:reset',
    description: 'Delete all synced mailbox messages and clear last-synced UID counters',
)]
final class MailboxResetCommand extends Command
{
    public function __construct(
        private readonly MailboxAccountRepository $accountRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Reset only mailbox account id')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getOption('id');

        if ($id !== null) {
            $account = $this->accountRepository->find((int) $id);
            $accounts = $account !== null ? [$account] : [];
        } else {
            /** @var list<MailboxAccount> $accounts */
            $accounts = $this->accountRepository->findAll();
        }

        if ($accounts === []) {
            $io->warning('No mailbox accounts found.');

            return Command::SUCCESS;
        }

        $accountIds = array_map(static fn (MailboxAccount $a): int => (int) $a->getId(), $accounts);

        $messageCount = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(MailboxMessage::class, 'm')
            ->andWhere('m.mailbox IN (:accounts)')
            ->setParameter('accounts', $accounts)
            ->getQuery()
            ->getSingleScalarResult();

        $io->writeln(sprintf(
            'Accounts: %d (%s)',
            \count($accounts),
            implode(', ', array_map(static fn (int $accountId): string => '#'.$accountId, $accountIds))
        ));
        $io->writeln(sprintf('Messages to delete: %d', $messageCount));
        $io->writeln('Will also clear lastSyncedRemoteUid / lastSyncedAt / lastSyncError.');

        if (!$input->getOption('force') && !$io->confirm('Delete all selected mailbox messages and reset sync counters?', false)) {
            $io->warning('Aborted.');

            return Command::SUCCESS;
        }

        $deleted = $this->entityManager->createQueryBuilder()
            ->delete(MailboxMessage::class, 'm')
            ->andWhere('m.mailbox IN (:accounts)')
            ->setParameter('accounts', $accounts)
            ->getQuery()
            ->execute();

        foreach ($accounts as $account) {
            $account->setLastSyncedRemoteUid(null);
            $account->setLastSyncedAt(null);
            $account->setLastSyncError(null);
        }
        $this->entityManager->flush();

        $io->success(sprintf('Deleted %d message(s). Sync counters cleared for %d account(s).', $deleted, \count($accounts)));
        $io->note('Run app:mailbox:sync to import messages again.');

        return Command::SUCCESS;
    }
}
