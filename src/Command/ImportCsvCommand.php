<?php

namespace App\Command;

use App\Service\CsvEntityImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:csv',
    description: 'Import CSV into Doctrine entity'
)]
class ImportCsvCommand extends Command
{
    public function __construct(
        private readonly CsvEntityImporter $importer,
        private readonly string $appDomain
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity class or short name')
            ->addArgument('type', InputArgument::OPTIONAL, 'Scalar or Association')
            ->addArgument('file', InputArgument::OPTIONAL, 'CSV file path');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entity = $input->getArgument('entity');
        if($entity === 'SetSiteSetting'){
            $this->importer->setSiteUrl($this->appDomain);
            return Command::SUCCESS;
        }
        $type = $input->getArgument('type');
        $file   = $input->getArgument('file')
            ?? './export/'.strtolower($entity) . '.csv';

        if (!str_contains($entity, '\\')) {
            $entity = 'App\\Entity\\' . ucfirst($entity);
        }

        try {
            if ($type === 'scalar') {
                $io->section('Importing scalar fields...');
                $this->importer->importScalars($entity, $file);
            }else{
                $io->section('Importing associations...');
                $this->importer->importAssociations($entity, $file);
            }


        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

