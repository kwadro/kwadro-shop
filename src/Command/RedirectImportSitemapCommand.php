<?php

namespace App\Command;

use App\Entity\Redirect;
use App\Entity\RedirectType;
use App\Repository\RedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:redirect:import-sitemap',
    description: 'Import source URLs from a sitemap.xml into Redirect entities (type=base by default)',
)]
final class RedirectImportSitemapCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RedirectRepository $redirectRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to sitemap.xml', '111/sitemap.xml')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Redirect type', RedirectType::Base->value)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Parse only, do not write DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getOption('file');
        if (!str_starts_with($file, '/')) {
            $file = $this->projectDir.'/'.ltrim($file, './');
        }

        if (!is_file($file)) {
            $io->error(sprintf('Sitemap not found: %s', $file));

            return Command::FAILURE;
        }

        $typeValue = (string) $input->getOption('type');
        $type = RedirectType::tryFrom($typeValue);
        if ($type === null) {
            $io->error(sprintf('Unknown type "%s". Allowed: %s', $typeValue, implode(', ', array_column(RedirectType::cases(), 'value'))));

            return Command::FAILURE;
        }

        $xml = file_get_contents($file);
        if ($xml === false) {
            $io->error('Cannot read sitemap file.');

            return Command::FAILURE;
        }

        preg_match_all('#<loc>([^<]+)</loc>#i', $xml, $matches);
        $locs = $matches[1] ?? [];
        if ($locs === []) {
            $io->warning('No <loc> entries found.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $created = 0;
        $skipped = 0;

        foreach ($locs as $loc) {
            $fromPath = Redirect::normalizePath($loc);
            $existing = $this->redirectRepository->findOneByFromPath($fromPath);
            if ($existing !== null) {
                ++$skipped;
                continue;
            }

            if ($dryRun) {
                $io->writeln(' + '.$fromPath);
                ++$created;
                continue;
            }

            $redirect = (new Redirect())
                ->setType($type)
                ->setFromPath($fromPath)
                ->setToPath(null)
                ->setEnabled(true)
                ->setStatusCode(301);

            $this->em->persist($redirect);
            ++$created;

            if ($created % 50 === 0) {
                $this->em->flush();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s %d redirect(s) type=%s (skipped existing: %d) from %s',
            $dryRun ? 'Would create' : 'Created',
            $created,
            $type->value,
            $skipped,
            $file,
        ));

        return Command::SUCCESS;
    }
}
