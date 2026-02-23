<?php

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:debug',
    description: 'Debug user authentication issues',
)]
class UserDebugCommand extends Command
{
    public function __construct(
        private UtilisateurRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the user to debug')
            ->addArgument('password', InputArgument::OPTIONAL, 'The password to verify');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Find user by email
        $user = $this->userRepository->findOneByEmail($email);

        if (!$user) {
            $io->error("User with email '{$email}' not found in database.");
            return Command::FAILURE;
        }

        $io->success("User found!");
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $user->getId()],
                ['Email', $user->getEmail()],
                ['Name', $user->getPrenom() . ' ' . $user->getNom()],
                ['Role', $user->getRole()],
                ['Symfony Role', $user->getRoleSymfony()],
                ['Password Hash Length', strlen($user->getPassword())],
            ]
        );

        if ($password) {
            $isPasswordValid = $this->passwordHasher->isPasswordValid($user, $password);
            $io->newLine();
            
            if ($isPasswordValid) {
                $io->success("✓ Password is CORRECT!");
            } else {
                $io->error("✗ Password is INCORRECT!");
                $io->warning("Make sure there are no extra spaces and capitalization is correct.");
            }
        }

        return Command::SUCCESS;
    }
}
