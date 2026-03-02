<?php

namespace App\Tests\Entity;

use App\Entity\DemandeAnalyse;
use App\Entity\FicheMedicale;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class PatientTest extends TestCase
{
    public function testDefaultRoleAndCollections(): void
    {
        $patient = new Patient();

        $this->assertSame(Utilisateur::ROLE_SIMPLE_PATIENT, $patient->getRole());
        $this->assertSame(Utilisateur::ROLE_PATIENT, $patient->getRoleSymfony());
        $this->assertSame([Utilisateur::ROLE_PATIENT], $patient->getRoles());
        $this->assertCount(0, $patient->getFicheMedicales());
        $this->assertCount(0, $patient->getRendezVous());
        $this->assertCount(0, $patient->getDemandeAnalyses());
    }

    public function testGetAgeReturnsNullWhenNoDate(): void
    {
        $patient = new Patient();
        $patient->setDateNaissance(null);

        $this->assertNull($patient->getAge());
    }

    public function testGetAgeReturnsZeroForToday(): void
    {
        $patient = new Patient();
        $patient->setDateNaissance(new \DateTimeImmutable('today'));

        $this->assertSame(0, $patient->getAge());
    }

    public function testFicheMedicaleRelation(): void
    {
        $patient = new Patient();
        $fiche = new FicheMedicale();

        $patient->addFicheMedicale($fiche);

        $this->assertTrue($patient->getFicheMedicales()->contains($fiche));
        $this->assertSame($patient, $fiche->getPatient());

        $patient->removeFicheMedicale($fiche);

        $this->assertFalse($patient->getFicheMedicales()->contains($fiche));
        $this->assertNull($fiche->getPatient());
    }

    public function testRendezVousRelation(): void
    {
        $patient = new Patient();
        $rendezVous = new RendezVous();

        $patient->addRendezVous($rendezVous);

        $this->assertTrue($patient->getRendezVous()->contains($rendezVous));
        $this->assertSame($patient, $rendezVous->getPatient());

        $patient->removeRendezVous($rendezVous);

        $this->assertFalse($patient->getRendezVous()->contains($rendezVous));
        $this->assertNull($rendezVous->getPatient());
    }

    public function testDemandeAnalyseRelation(): void
    {
        $patient = new Patient();
        $demande = new DemandeAnalyse();

        $patient->addDemandeAnalyse($demande);

        $this->assertTrue($patient->getDemandeAnalyses()->contains($demande));
        $this->assertSame($patient, $demande->getPatient());

        $patient->removeDemandeAnalyse($demande);

        $this->assertFalse($patient->getDemandeAnalyses()->contains($demande));
        $this->assertNull($demande->getPatient());
    }
}
