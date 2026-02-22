<?php

namespace App\DataFixtures;

use App\Entity\Administrateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // ✅ Si tu as déjà un admin avec cet email, ne rien faire
        // (si tu n'as pas de repository injecté, tu peux ignorer ce check,
        // mais le mieux est de le faire via Option B)
        $admin = new Administrateur();
        $admin->setEmail('admin@sahty.tn');
        $admin->setNom('Admin');
        $admin->setPrenom('System');

        // selon ton projet : role stocké en string "admin"
        $admin->setRole('admin');

        $admin->setPassword(
            $this->hasher->hashPassword($admin, 'Admin@12345')
        );

        $manager->persist($admin);
        $manager->flush();
    }
}
