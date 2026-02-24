<?php

namespace App\Command;

use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test send email functionality',
    hidden: false,
)]
class TestMailCommand extends Command
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Test an email send')
            ->addArgument('email', InputArgument::REQUIRED, 'Email destination');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->info("Attempting to send test email to: $email");

        try {
            $testEmail = (new Email())
                ->from('maramouerghi1234@gmail.com')
                ->to($email)
                ->subject('Test Email - Sahty System')
                ->text('This is a test email from Sahty system to verify email configuration.');

            $success = $this->emailService->send($testEmail);

            if ($success) {
                $io->success('Email sent successfully!');
                return Command::SUCCESS;
            } else {
                $io->error('Email sending failed. Check the logs for details.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Exception occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
