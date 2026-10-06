<?php

namespace App\Command;

use App\Entity\ProductImport;
use App\Entity\ProductImportRunStatus;
use App\Repository\ProductImportRepository;
use App\Service\ProductImport\SivitekProductImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:product-import:run',
    description: 'Run a configured product import (sync price, stock and description)',
)]
final class ProductImportRunCommand extends Command
{
    public function __construct(
        private readonly ProductImportRepository $productImportRepository,
        private readonly SivitekProductImportService $sivitekProductImportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Import code', ProductImport::CODE_SIVITEK)
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Import entity id')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and report without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $id = $input->getOption('id');

        if ($id !== null && $id !== '') {
            $import = $this->productImportRepository->find((int) $id);
        } else {
            $import = $this->productImportRepository->findOneByCode((string) $input->getOption('code'));
        }

        if ($import === null) {
            $io->error('Product import not found.');

            return Command::FAILURE;
        }

        if (!$import->isActive()) {
            $io->error(sprintf('Import "%s" is inactive.', $import->getName()));

            return Command::FAILURE;
        }

        $io->title(sprintf('Running import: %s (%s)', $import->getName(), $import->getCode()));

        $run = match ($import->getCode()) {
            ProductImport::CODE_SIVITEK => $this->sivitekProductImportService->run($import, $dryRun),
            default => throw new \RuntimeException(sprintf('Unsupported import code: %s', $import->getCode())),
        };

        if ($run->getStatus() === ProductImportRunStatus::Failed) {
            $io->error($run->getErrorMessage() ?? $run->getResult() ?? 'Import failed.');

            return Command::FAILURE;
        }

        $io->success(($dryRun ? '[dry-run] ' : '').($run->getResult() ?? 'Done.'));

        return Command::SUCCESS;
    }
}
