<?php

namespace App\Command;

use App\Service\Cache\FullPageCachePurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fpc:purge',
    description: 'Clear the full page cache (PHP HttpCache dir or nginx FPC_CACHE_DIR)',
)]
final class FullPageCachePurgeCommand extends Command
{
    public function __construct(
        private readonly FullPageCachePurger $purger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $removed = $this->purger->purge();

        if ($removed < 0) {
            $io->note('Full page cache directory is empty or not configured — nothing to purge.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('Full page cache cleared: %d files removed.', $removed));

        return Command::SUCCESS;
    }
}
