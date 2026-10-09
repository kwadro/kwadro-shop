<?php

namespace App\Command;

use App\Service\Image\BackgroundRemoverService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:image:remove-background',
    description: 'Remove light/white background and clean fringe; save transparent PNG',
)]
final class ImageRemoveBackgroundCommand extends Command
{
    public function __construct(
        private readonly BackgroundRemoverService $backgroundRemover,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'Input image path')
            ->addArgument('output', InputArgument::REQUIRED, 'Output PNG path')
            ->addOption('erode', null, InputOption::VALUE_REQUIRED, 'Edge shrink in pixels (0-4)', '1')
            ->addOption('strength', null, InputOption::VALUE_REQUIRED, 'soft|normal|strong', 'normal')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'full|contour (contour clears only exterior background)', BackgroundRemoverService::MODE_FULL)
            ->addOption('max-side', null, InputOption::VALUE_REQUIRED, 'Max image side in px', (string) BackgroundRemoverService::MAX_SIDE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $inPath = (string) $input->getArgument('input');
        $outPath = (string) $input->getArgument('output');
        $strength = (string) $input->getOption('strength');
        $erode = max(0, min(4, (int) $input->getOption('erode')));
        $maxSide = max(400, (int) $input->getOption('max-side'));
        $mode = (string) $input->getOption('mode');
        if ($mode !== BackgroundRemoverService::MODE_CONTOUR) {
            $mode = BackgroundRemoverService::MODE_FULL;
        }

        [$whiteThreshold, $softThreshold, $edgeDarken] = match ($strength) {
            'soft' => [230, 210, 65],
            'strong' => [200, 185, 50],
            default => [215, 200, 55],
        };

        try {
            $this->backgroundRemover->removeFromPath($inPath, $outPath, [
                'erode' => $erode,
                'whiteThreshold' => $whiteThreshold,
                'softThreshold' => $softThreshold,
                'edgeDarken' => $edgeDarken,
                'maxSide' => $maxSide,
                'mode' => $mode,
            ]);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Saved transparent PNG: %s', $outPath));

        return Command::SUCCESS;
    }
}
