<?php

namespace App\Tests\Service;

use App\Entity\FicheMedicale;
use App\Entity\Patient;
use PHPUnit\Framework\TestCase;

class FichemedicaManagerTestPhpTest extends TestCase
{
    public function testCalculerImcSetsExpectedValueAndCategory(): void
    {
        $fiche = new FicheMedicale();
        $fiche->setTaille('1.75');
        $fiche->setPoids('70');

        $fiche->calculerImc();

        $this->assertSame(22.86, $fiche->getImc());
        $this->assertSame('Normal', $fiche->getCategorieImc());
    }

    public function testCalculerImcResetsValuesWhenDataIsInvalid(): void
    {
        $fiche = new FicheMedicale();
        $fiche->setTaille('0');
        $fiche->setPoids('70');

        $fiche->calculerImc();

        $this->assertNull($fiche->getImc());
        $this->assertNull($fiche->getCategorieImc());
    }

    public function testSetCreeLeValueSetsDateAndDefaultStatus(): void
    {
        $fiche = new FicheMedicale();

        $fiche->setCreeLeValue();

        $this->assertInstanceOf(\DateTimeInterface::class, $fiche->getCreeLe());
        $this->assertSame('actif', $fiche->getStatut());
    }

    public function testGetResumeReturnsExpectedTextWithPatientAndDate(): void
    {
        $patient = new Patient();
        $patient->setNom('Doe');
        $patient->setPrenom('Jane');

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        $fiche->setCreeLe(new \DateTime('2025-01-15'));

        $this->assertSame('Fiche de Doe Jane - 15/01/2025', $fiche->getResume());
    }
}
