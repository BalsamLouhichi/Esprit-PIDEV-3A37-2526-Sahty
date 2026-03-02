<?php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Entity\Utilisateur;
use App\Service\InscriptionEvenementManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InscriptionEvenementManagerTest extends TestCase
{
    private InscriptionEvenementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new InscriptionEvenementManager();
    }

    public function testValidateThrowsExceptionWhenEvenementOrUtilisateurMissing(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setStatut('en_attente');
        $inscription->setDateInscription(new \DateTime('-1 day'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'evenement et l\'utilisateur sont obligatoires.');

        $this->manager->validate($inscription);
    }

    public function testValidateThrowsExceptionWhenDateInscriptionIsFuture(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setStatut('en_attente');
        $inscription->setDateInscription(new \DateTime('+1 day'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'inscription ne peut pas etre dans le futur.');

        $this->manager->validate($inscription);
    }

    public function testValidateThrowsExceptionWhenPresentButNotConfirmee(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setDateInscription(new \DateTime('-1 day'));
        $inscription->setStatut('en_attente');
        $inscription->setPresent(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Une inscription marquee presente doit etre confirmee.');

        $this->manager->validate($inscription);
    }

    public function testValidateReturnsTrueWhenInscriptionIsValid(): void
    {
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement(new Evenement());
        $inscription->setUtilisateur(new Utilisateur());
        $inscription->setDateInscription(new \DateTime('-1 day'));
        $inscription->setStatut('confirmee');
        $inscription->setPresent(true);

        self::assertTrue($this->manager->validate($inscription));
    }
}
