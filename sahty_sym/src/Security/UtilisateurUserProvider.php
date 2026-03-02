<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UtilisateurUserProvider implements UserProviderInterface
{
    public function __construct(
        private UtilisateurRepository $userRepository,
        private EntityManagerInterface $entityManager
    )
    {
    }

    /**
     * Load user by username (email in our case)
     * This is called by Symfony's form authenticator
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try to find user by email (case-insensitive)
        $user = $this->userRepository->findOneByEmail($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('Email "%s" does not exist.', $identifier));
        }

        return $user;
    }

    /**
     * Refresh user from database
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        // Reload using the concrete class to avoid unnecessary JOINs on sibling subclasses.
        $refreshedUser = $this->entityManager
            ->getRepository($user::class)
            ->find($user->getId());

        if (!$refreshedUser) {
            throw new UserNotFoundException(sprintf('User with id %d not found', $user->getId()));
        }

        return $refreshedUser;
    }

    /**
     * Tells if this provider can handle the given user.
     */
    public function supportsClass(string $class): bool
    {
        // Support Utilisateur and all its subclasses (Medecin, Patient, etc.)
        return $class === Utilisateur::class || is_subclass_of($class, Utilisateur::class);
    }

    /**
     * Legacy method for backward compatibility
     */
    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }
}
