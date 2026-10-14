<?php

namespace App\Command;

use App\Repository\MailboxAccountRepository;
use App\Service\Mail\Mailbox\MailboxImapSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mailbox:sync',
    description: 'Sync emails from active mailbox accounts via IMAP',
)]
final class MailboxSyncCommand extends Command
{
    public function __construct(
        private readonly MailboxAccountRepository $accountRepository,
        private readonly MailboxImapSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Sync only mailbox account id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\function_exists('imap_open')) {
            $io->error(sprintf(
                'PHP extension ext-imap is missing in %s. Run: /usr/bin/php8.2 bin/console app:mailbox:sync',
                PHP_BINARY,
            ));

            return Command::FAILURE;
        }

        $id = $input->getOption('id');

        if ($id !== null) {
            $account = $this->accountRepository->find((int) $id);
            $accounts = $account !== null ? [$account] : [];
        } else {
            $accounts = $this->accountRepository->findActive();
        }

        if ($accounts === []) {
            $io->warning('No mailbox accounts to sync.');

            return Command::SUCCESS;
        }

        $failed = false;
        foreach ($accounts as $account) {
            $io->section(sprintf('#%d %s <%s>', $account->getId(), $account->getName(), $account->getEmail()));
            $result = $this->syncService->sync($account);
            if ($result['error'] !== null) {
                $failed = true;
                $io->error($result['error']);
                continue;
            }
            $io->success(sprintf('Imported: %d, updated: %d', $result['imported'], $result['updated']));
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
