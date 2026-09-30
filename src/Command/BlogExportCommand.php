<?php

namespace App\Command;

use App\Service\Blog\BlogContentPackageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:blog:export',
    description: 'Export blog categories, articles and related uploads into a portable zip package',
)]
final class BlogExportCommand extends Command
{
    public function __construct(
        private readonly BlogContentPackageService $packageService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Output zip path (default: var/export/blog-package.zip)')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Filter by site code')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Filter by locale code (e.g. uk)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $result = $this->packageService->export(
                $input->getOption('site'),
                $input->getOption('locale'),
                $input->getOption('file'),
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Exported %d categories, %d articles, %d assets → %s',
            $result['categories'],
            $result['articles'],
            $result['assets'],
            $result['path'],
        ));

        return Command::SUCCESS;
    }
}
