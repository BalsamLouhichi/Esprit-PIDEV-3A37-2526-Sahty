<?php

namespace App\Tests\Entity;

use App\Entity\PasswordResetToken;
use PHPUnit\Framework\TestCase;

class PasswordResetTokenTest extends TestCase
{
    public function testIsExpiredReturnsTrueForPastExpiry(): void
    {
        $token = new PasswordResetToken();
        $token->setExpiresAt((new \DateTimeImmutable())->modify('-1 hour'));

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredReturnsFalseForFutureExpiry(): void
    {
        $token = new PasswordResetToken();
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));

        $this->assertFalse($token->isExpired());
    }

    public function testIsValidReturnsFalseWhenUsed(): void
    {
        $token = new PasswordResetToken();
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $token->setIsUsed(true);

        $this->assertFalse($token->isValid());
    }

    public function testIsValidReturnsFalseWhenExpired(): void
    {
        $token = new PasswordResetToken();
        $token->setExpiresAt((new \DateTimeImmutable())->modify('-1 hour'));
        $token->setIsUsed(false);

        $this->assertFalse($token->isValid());
    }

    public function testIsValidReturnsTrueWhenNotUsedAndNotExpired(): void
    {
        $token = new PasswordResetToken();
        $token->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $token->setIsUsed(false);

        $this->assertTrue($token->isValid());
    }
}
