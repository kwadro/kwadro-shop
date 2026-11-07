<?php

namespace App\Command;

use App\Entity\ProductImport;
use App\Entity\ProductImportRunStatus;
use App\Repository\ProductImportRepository;
use App\Service\ProductImport\LugiProductImportService;
use App\Service\ProductImport\ProductImportRunLock;
use App\Service\ProductImport\SivitekProductImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
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
        private readonly LugiProductImportService $lugiProductImportService,
        private readonly ProductImportRunLock $runLock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Import code', ProductImport::CODE_SIVITEK)
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Import entity id')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse and report without writing')
            ->addOption('no-images', null, InputOption::VALUE_NONE, 'Skip feed image download and image assignment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        @ini_set('memory_limit', '512M');
        // Stay behind web requests when the shop and the import share one server.
        if (\function_exists('proc_nice')) {
            @proc_nice(10);
        }

        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $withImages = !(bool) $input->getOption('no-images');
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

        $importId = (int) $import->getId();
        $lock = $this->runLock->acquire($importId);
        if ($lock === null) {
            $io->error('This import is already running.');

            return Command::FAILURE;
        }

        try {
            $io->title(sprintf('Running import: %s (%s)', $import->getName(), $import->getCode()));

            $run = match ($import->getCode()) {
                ProductImport::CODE_SIVITEK => $this->sivitekProductImportService->run($import, $dryRun),
                ProductImport::CODE_LUGI => $this->lugiProductImportService->run($import, $dryRun, $withImages),
                default => throw new \RuntimeException(sprintf('Unsupported import code: %s', $import->getCode())),
            };

            if ($run->getStatus() === ProductImportRunStatus::Failed) {
                $io->error($run->getErrorMessage() ?? $run->getResult() ?? 'Import failed.');

                return Command::FAILURE;
            }

            $io->success(($dryRun ? '[dry-run] ' : '').($run->getResult() ?? 'Done.'));

            if (!$withImages) {
                return Command::SUCCESS;
            }

            return $this->assignImportImages((string) $import->getCode(), $dryRun, $output, $io);
        } finally {
            $this->runLock->release($lock);
        }
    }

    private function assignImportImages(string $importCode, bool $dryRun, OutputInterface $output, SymfonyStyle $io): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            $io->warning('Console application is not available; skipped app:product:assign-import-images.');

            return Command::SUCCESS;
        }

        $arguments = ['import-code' => $importCode];
        if ($dryRun) {
            $arguments['--dry-run'] = true;
        }

        $assignInput = new ArrayInput($arguments);
        $assignInput->setInteractive(false);

        $exitCode = $application->find('app:product:assign-import-images')->run($assignInput, $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error(sprintf('app:product:assign-import-images %s failed (exit code %d).', $importCode, $exitCode));
        }

        return $exitCode;
    }
}
