<?php

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Reset a user password with proper hashing'
)]
class ResetPasswordCommand extends Command
{
    public function __construct(
        private UtilisateurRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the user')
            ->addArgument('password', InputArgument::REQUIRED, 'The new password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $newPassword = $input->getArgument('password');

        // Find user by email
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user) {
            $io->error("User with email '{$email}' not found!");
            return Command::FAILURE;
        }

        // Hash the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        
        // Update password
        $user->setPassword($hashedPassword);
        $this->em->flush();

        $io->success("Password reset successfully for {$email}");
        $io->info("Old hash length: ~11 characters");
        $io->info("New hash length: " . strlen($hashedPassword) . " characters (bcrypt standard)");
        
        return Command::SUCCESS;
    }
}
