<?php

namespace App\Security;

use App\Entity\ResponsableLaboratoire;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Utilisateur && !$user->isEstActif()) {
            throw new CustomUserMessageAccountStatusException('Votre compte est desactive. Contactez l\'administrateur.');
        }

        if ($user instanceof ResponsableLaboratoire) {
            $laboratoire = $user->getLaboratoire();
            if ($laboratoire && !$laboratoire->isDisponible()) {
                throw new CustomUserMessageAccountStatusException('Votre laboratoire est desactive. Contactez l\'administrateur.');
            }
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No-op
    }
}
