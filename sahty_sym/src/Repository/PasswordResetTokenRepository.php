<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findValidTokenByToken(string $token): ?PasswordResetToken
    {
        $resetToken = $this->findOneBy(['token' => $token]);
        
        if (!$resetToken) {
            return null;
        }

        // Vérifier si le token est valide
        if (!$resetToken->isValid()) {
            return null;
        }

        return $resetToken;
    }
}
