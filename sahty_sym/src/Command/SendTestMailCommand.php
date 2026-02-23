<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:send-test-mail',
    description: 'Envoie un email de test avec un PDF en piece jointe.'
)]
class SendTestMailCommand extends Command
{
    private MailerInterface $mailer;
    private string $projectDir;

    public function __construct(MailerInterface $mailer, KernelInterface $kernel)
    {
        parent::__construct();
        $this->mailer = $mailer;
        $this->projectDir = $kernel->getProjectDir();
    }

    protected function configure(): void
    {
        $this
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Destinataire de test')
            ->addOption(
                'pdf',
                null,
                InputOption::VALUE_OPTIONAL,
                'Chemin relatif vers le PDF',
                'public/uploads/resultats/dashboard-1-6989c384520ed.pdf'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = (string) $input->getOption('to');
        $pdf = (string) $input->getOption('pdf');

        if ($to === '') {
            $output->writeln('<error>Option --to obligatoire.</error>');
            return Command::INVALID;
        }

        $filePath = rtrim($this->projectDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($pdf, '/\\');
        if (!is_file($filePath)) {
            $output->writeln('<error>PDF introuvable: ' . $filePath . '</error>');
            return Command::FAILURE;
        }

        $email = (new Email())
            ->from('no-reply@sahty.local')
            ->to($to)
            ->subject('Test mail - resultat PDF')
            ->text('Ceci est un email de test avec un PDF en piece jointe.')
            ->attachFromPath($filePath, 'resultat-test.pdf', 'application/pdf');

        $this->mailer->send($email);

        $output->writeln('<info>Email envoye a ' . $to . '.</info>');
        return Command::SUCCESS;
    }
}
