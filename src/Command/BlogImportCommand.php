<?php

namespace App\Command;

use App\Service\Blog\BlogContentPackageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:blog:import',
    description: 'Import blog categories, articles and uploads from a package created by app:blog:export',
)]
final class BlogImportCommand extends Command
{
    public function __construct(
        private readonly BlogContentPackageService $packageService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Package zip path', 'var/export/blog-package.zip')
            ->addOption('site-id', null, InputOption::VALUE_REQUIRED, 'Force target site id (default: match by site_code, fallback #1)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate package without writing to DB/files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');
        $siteId = $input->getOption('site-id');
        $dryRun = (bool) $input->getOption('dry-run');

        if (!str_starts_with($file, '/') && !preg_match('#^[A-Za-z]:\\\\#', $file)) {
            $file = getcwd().'/'.$file;
        }

        try {
            $stats = $this->packageService->import(
                $file,
                $siteId !== null ? (int) $siteId : null,
                $dryRun,
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->success(sprintf(
                'Dry-run OK: %d categories, %d articles, %d assets in package',
                $stats['categories_created'],
                $stats['articles_created'],
                $stats['assets'],
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Imported categories +%d/~%d, articles +%d/~%d, assets %d',
            $stats['categories_created'],
            $stats['categories_updated'],
            $stats['articles_created'],
            $stats['articles_updated'],
            $stats['assets'],
        ));

        return Command::SUCCESS;
    }
}
