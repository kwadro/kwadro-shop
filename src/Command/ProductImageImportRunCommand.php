<?php

namespace App\Command;

use App\Entity\ProductImport;
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
    name: 'app:product-image-import:run',
    description: 'Download product images',
)]
final class ProductImageImportRunCommand extends Command
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
            ->addOption('patch', null, InputOption::VALUE_OPTIONAL, 'Convert  specific directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        @ini_set('memory_limit', '512M');
        // Stay behind web requests when the shop and the import share one server.
        if (\function_exists('proc_nice')) {
            @proc_nice(10);
        }

        $io = new SymfonyStyle($input, $output);

        $id = $input->getOption('id');

        if ($id !== null && $id !== '') {
            $import = $this->productImportRepository->find((int)$id);
        } else {
            $import = $this->productImportRepository->findOneByCode((string)$input->getOption('code'));
        }

        if ($import === null) {
            $io->error('Product import not found.');

            return Command::FAILURE;
        }

        if (!$import->isActive()) {
            $io->error(sprintf('Import "%s" is inactive.', $import->getName()));

            return Command::FAILURE;
        }

        $importId = (int)$import->getId();
        $lock = $this->runLock->acquire($importId);

        if ($lock === null) {
            $io->error('This import is already running.');

            return Command::FAILURE;
        }

        try {
            $io->title(sprintf('Running import: %s (%s)', $import->getName(), $import->getCode()));
            $patch = $input->getOption('patch');
            if(!$patch){
                match ($import->getCode()) {
                    ProductImport::CODE_SIVITEK => $this->sivitekProductImportService->runImage($import),
                    ProductImport::CODE_LUGI, ProductImport::CODE_TOYSI => $this->lugiProductImportService->runImage($import),
                    default => throw new \RuntimeException(sprintf('Unsupported import code: %s', $import->getCode())),
                };
            }else{
                match ($import->getCode()) {
                    ProductImport::CODE_SIVITEK => $this->sivitekProductImportService->runImageByPatch($patch),
                    ProductImport::CODE_LUGI, ProductImport::CODE_TOYSI => $this->lugiProductImportService->runImageByPatch($patch),
                    default => throw new \RuntimeException(sprintf('Unsupported import code: %s', $import->getCode())),
                };
            }


            $io->success('Done.');
            return Command::SUCCESS;
        } finally {
            $this->runLock->release($lock);
        }
    }

    private function assignImportImages(
        string $importCode,
        bool $dryRun,
        OutputInterface $output,
        SymfonyStyle $io
    ): int {
        $application = $this->getApplication();
        if ($application === null) {
            $io->warning('Console application is not available; skipped app:product:assign-import-images.');

            return Command::SUCCESS;
        }

        $arguments = ['import-code' => $importCode];

        $assignInput = new ArrayInput($arguments);
        $assignInput->setInteractive(false);

        $exitCode = $application->find('app:product:assign-import-images')->run($assignInput, $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error(sprintf('app:product:assign-import-images %s failed (exit code %d).', $importCode, $exitCode));
        }

        return $exitCode;
    }

    private function formatElapsed(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        $minutes = (int)floor($seconds / 60);
        $remain = $seconds - ($minutes * 60);

        if ($minutes < 60) {
            return sprintf('%dm %.1fs', $minutes, $remain);
        }

        $hours = (int)floor($minutes / 60);
        $minutes %= 60;

        return sprintf('%dh %dm %.1fs', $hours, $minutes, $remain);
    }
}
