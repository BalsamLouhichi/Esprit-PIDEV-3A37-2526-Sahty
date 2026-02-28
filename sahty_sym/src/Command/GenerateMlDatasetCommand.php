<?php

namespace App\Command;

use App\Service\MlDatasetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:ml:dataset',
    description: 'Genere le dataset ML (CSV) a partir de la base de donnees.'
)]
class GenerateMlDatasetCommand extends Command
{
    public function __construct(
        private readonly MlDatasetService $datasetService,
        private readonly KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'out',
            null,
            InputOption::VALUE_REQUIRED,
            'Chemin du fichier CSV de sortie',
            'ml/dataset.csv'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $out = (string) $input->getOption('out');
        $projectDir = rtrim($this->kernel->getProjectDir(), DIRECTORY_SEPARATOR);

        if ($out === '') {
            $output->writeln('<error>Chemin de sortie invalide.</error>');
            return Command::INVALID;
        }

        $path = $out;
        if (!str_starts_with($out, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $out)) {
            $path = $projectDir . DIRECTORY_SEPARATOR . $out;
        }

        try {
            $rows = $this->datasetService->buildDataset($path);
        } catch (\Throwable $e) {
            $output->writeln('<error>Erreur generation dataset: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Dataset ecrit: %s (%d lignes)', $path, $rows));
        return Command::SUCCESS;
    }
}
