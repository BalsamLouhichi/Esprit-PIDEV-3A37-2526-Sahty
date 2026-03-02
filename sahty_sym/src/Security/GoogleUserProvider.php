<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<UserInterface>
 */
class GoogleUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$this->supportsClass($user::class)) {
            throw new UnsupportedUserException();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, UserInterface::class, true);
    }
}
