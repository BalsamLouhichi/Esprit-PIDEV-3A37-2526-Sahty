<?php

namespace App\Command;

use App\Service\EmailService;
use App\Service\MonthlyReportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:report:monthly',
    description: 'Genere le rapport mensuel admin et peut l\'envoyer par email.'
)]
class MonthlyReportCommand extends Command
{
    private MonthlyReportService $reportService;
    private EmailService $emailService;
    private KernelInterface $kernel;
    private string $reportSender;
    private string $reportRecipients;

    public function __construct(
        MonthlyReportService $reportService,
        EmailService $emailService,
        KernelInterface $kernel,
        string $reportSender,
        string $reportRecipients
    ) {
        parent::__construct();
        $this->reportService = $reportService;
        $this->emailService = $emailService;
        $this->kernel = $kernel;
        $this->reportSender = $reportSender;
        $this->reportRecipients = $reportRecipients;
    }

    protected function configure(): void
    {
        $this
            ->addOption('month', null, InputOption::VALUE_OPTIONAL, 'Mois au format YYYY-MM')
            ->addOption('send', null, InputOption::VALUE_NONE, 'Envoyer le rapport par email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $monthOption = $input->getOption('month');
        $month = null;
        if (is_string($monthOption) && $monthOption !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m', $monthOption);
            if (!$parsed) {
                $output->writeln('<error>Format de mois invalide. Utilisez YYYY-MM.</error>');
                return Command::INVALID;
            }
            $month = $parsed->setTime(0, 0)->modify('first day of this month');
        }

        $report = $this->reportService->buildReport($month);
        $reportsDir = $this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports';
        $filePath = $this->reportService->savePdf($report, $reportsDir);

        $output->writeln('<info>Rapport genere:</info> ' . $filePath);

        if ($input->getOption('send')) {
            $recipients = array_filter(array_map('trim', explode(',', $this->reportRecipients)));
            if (!$recipients) {
                $output->writeln('<error>Aucun destinataire configure (REPORT_RECIPIENTS).</error>');
                return Command::FAILURE;
            }

            $subject = sprintf('Rapport mensuel admin - %s', $report['period']['start']->format('m/Y'));
            $body = 'Veuillez trouver en piece jointe le rapport mensuel admin.';

            $sent = $this->emailService->sendWithAttachment(
                $this->reportSender,
                $recipients,
                $subject,
                $body,
                $filePath,
                basename($filePath)
            );

            if (!$sent) {
                $output->writeln('<error>Echec de l\'envoi email.</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>Email envoye.</info>');
        }

        return Command::SUCCESS;
    }
}
