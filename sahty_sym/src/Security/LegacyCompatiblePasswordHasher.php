<?php

namespace App\Security;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class LegacyCompatiblePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        if ($this->isLegacySha256Hash($hashedPassword)) {
            return hash_equals(hash('sha256', $plainPassword), $hashedPassword);
        }

        return password_verify($plainPassword, $hashedPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        if ($this->isLegacySha256Hash($hashedPassword)) {
            return true;
        }

        return password_needs_rehash($hashedPassword, PASSWORD_BCRYPT);
    }

    private function isLegacySha256Hash(string $hashedPassword): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $hashedPassword);
    }
}
