<?php

namespace App\Tests\Entity;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class UtilisateurTest extends TestCase
{
    public function testDefaultState(): void
    {
        $user = new Utilisateur();

        $this->assertTrue($user->isEstActif());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreeLe());
        $this->assertSame('ROLE_USER', $user->getRoleSymfony());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetUserIdentifierAndPassword(): void
    {
        $user = new Utilisateur();
        $user->setEmail('user@example.test');
        $user->setPassword('hashed-password');

        $this->assertSame('user@example.test', $user->getUserIdentifier());
        $this->assertSame('hashed-password', $user->getPassword());
    }

    public function testNomComplet(): void
    {
        $user = new Utilisateur();
        $user->setPrenom('Jane');
        $user->setNom('Doe');

        $this->assertSame('Jane Doe', $user->getNomComplet());
    }

    public function testRoleMappingAndHelpers(): void
    {
        $user = new Utilisateur();
        $user->setRole(Utilisateur::ROLE_SIMPLE_PATIENT);

        $this->assertSame(Utilisateur::ROLE_PATIENT, $user->getRoleSymfony());
        $this->assertSame([Utilisateur::ROLE_PATIENT], $user->getRoles());
        $this->assertTrue($user->hasRole(Utilisateur::ROLE_SIMPLE_PATIENT));
        $this->assertTrue($user->hasRoleSymfony(Utilisateur::ROLE_PATIENT));
        $this->assertFalse($user->hasRole(Utilisateur::ROLE_SIMPLE_MEDECIN));
    }

    public function testSetRoleRejectsInvalidValue(): void
    {
        $user = new Utilisateur();

        $this->expectException(\InvalidArgumentException::class);
        $user->setRole('invalid-role');
    }

    public function testGetAgeReturnsNullWhenNoDate(): void
    {
        $user = new Utilisateur();
        $user->setDateNaissance(null);

        $this->assertNull($user->getAge());
    }

    public function testGetAgeReturnsZeroForToday(): void
    {
        $user = new Utilisateur();
        $user->setDateNaissance(new \DateTimeImmutable('today'));

        $this->assertSame(0, $user->getAge());
    }
}
