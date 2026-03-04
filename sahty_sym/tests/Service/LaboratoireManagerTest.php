<?php

namespace App\Tests\Service;

use App\Entity\Laboratoire;
use App\Service\LaboratoireManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LaboratoireManagerTest extends TestCase
{
    private LaboratoireManager $manager;

    protected function setUp(): void
    {
        $this->manager = new LaboratoireManager();
    }

    // Test nominal: toutes les donnees sont valides, la validation doit retourner true.
    public function testValidateReturnsTrueForValidLaboratoire(): void
    {
        $laboratoire = $this->buildValidLaboratoire();

        $this->assertTrue($this->manager->validate($laboratoire));
    }

    // Regle metier: le nom est obligatoire.
    public function testValidateThrowsExceptionWhenNomIsEmpty(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setNom('');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Regle metier: la ville est obligatoire.
    public function testValidateThrowsExceptionWhenVilleIsEmpty(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setVille('');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Regle metier: l'adresse est obligatoire.
    public function testValidateThrowsExceptionWhenAdresseIsEmpty(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setAdresse('');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Regle metier: le telephone est obligatoire.
    public function testValidateThrowsExceptionWhenTelephoneIsEmpty(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setTelephone('');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Regle metier: telephone invalide (format non conforme) doit etre refuse.
    public function testValidateThrowsExceptionWhenTelephoneIsInvalid(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setTelephone('123');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Cas valide: telephone avec 8 chiffres sans prefixe +216 doit passer.
    public function testValidateAcceptsTelephoneWithEightDigits(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setTelephone('12345678');

        $this->assertTrue($this->manager->validate($laboratoire));
    }

    // Regle metier: si email renseigne, il doit etre valide.
    public function testValidateThrowsExceptionWhenEmailIsInvalid(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setEmail('not-an-email');

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Regle metier: latitude hors borne [-90, 90] doit etre refusee.
    public function testValidateThrowsExceptionWhenLatitudeOutOfRange(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setLatitude(120.0);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Regle metier: longitude hors borne [-180, 180] doit etre refusee.
    public function testValidateThrowsExceptionWhenLongitudeOutOfRange(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setLongitude(250.0);

        $this->expectException(InvalidArgumentException::class);
        $this->manager->validate($laboratoire);
    }

    // Fonctionnalite: isEstActif doit etre un alias fiable de disponible.
    public function testIsEstActifReflectsDisponibiliteFlag(): void
    {
        $laboratoire = $this->buildValidLaboratoire();

        $laboratoire->setDisponible(true);
        $this->assertTrue($laboratoire->isEstActif());

        $laboratoire->setDisponible(false);
        $this->assertFalse($laboratoire->isEstActif());
    }

    // Fonctionnalite: l'adresse complete doit concatener adresse + ville.
    public function testGetAdresseCompleteReturnsFormattedAddress(): void
    {
        $laboratoire = $this->buildValidLaboratoire();

        $this->assertSame('12 Rue de la Sante, Tunis', $laboratoire->getAdresseComplete());
    }

    // Fonctionnalite: __toString doit retourner le nom du laboratoire.
    public function testToStringReturnsLaboratoireName(): void
    {
        $laboratoire = $this->buildValidLaboratoire();

        $this->assertSame('BioLab', (string) $laboratoire);
    }

    // Fonctionnalite service: la validation ne doit pas alterer l'etat metier du laboratoire.
    public function testValidateDoesNotMutateBusinessState(): void
    {
        $laboratoire = $this->buildValidLaboratoire();
        $laboratoire->setDisponible(false);
        $beforeCreeLe = $laboratoire->getCreeLe();

        $this->assertTrue($this->manager->validate($laboratoire));
        $this->assertFalse($laboratoire->isDisponible());
        $this->assertSame($beforeCreeLe, $laboratoire->getCreeLe());
    }

    private function buildValidLaboratoire(): Laboratoire
    {
        $laboratoire = new Laboratoire();
        $laboratoire->setNom('BioLab');
        $laboratoire->setVille('Tunis');
        $laboratoire->setAdresse('12 Rue de la Sante');
        $laboratoire->setTelephone('+216 12345678');
        $laboratoire->setLatitude(36.8065);
        $laboratoire->setLongitude(10.1815);
        $laboratoire->setEmail('contact@biolab.tn');

        return $laboratoire;
    }
}

