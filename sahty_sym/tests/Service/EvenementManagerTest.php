<?php
// tests/Service/EvenementManagerTest.php

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Service\EvenementManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EvenementManagerTest extends TestCase
{
    private EvenementManager $manager;

    protected function setUp(): void
    {
        $this->manager = new EvenementManager();
    }

    private function createValidEvenement(): Evenement
    {
        $e = new Evenement();
        $e->setDateDebut(new \DateTime('2026-03-10 10:00:00'));
        $e->setDateFin(new \DateTime('2026-03-10 12:00:00'));
        $e->setPlacesMax(100);
        $e->setTitre('Forum Sahty 2026');
        $e->setTarif(50.0);

        return $e;
    }

    public function testValidateReturnsTrueWhenDatesAndPlacesAreValid(): void
    {
        $e = $this->createValidEvenement();

        $this->assertTrue($this->manager->validate($e));
    }

    public function testValidateThrowsWhenDateFinIsBeforeDateDebut(): void
    {
        $e = $this->createValidEvenement();
        $e->setDateDebut(new \DateTime('2026-03-10 15:00:00'));
        $e->setDateFin(new \DateTime('2026-03-10 12:00:00'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit etre posterieure a la date de debut.');

        $this->manager->validate($e);
    }

    public function testValidateThrowsWhenPlacesMaxIsLessThanOrEqualToZero(): void
    {
        $e = $this->createValidEvenement();
        $e->setPlacesMax(0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de places doit etre superieur a zero.');

        $this->manager->validate($e);
    }

    public function testValidateThrowsWhenPlacesMaxIsGreaterThan10000(): void
    {
        $e = $this->createValidEvenement();
        $e->setPlacesMax(10001);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de places ne peut pas depasser 10000.');

        $this->manager->validate($e);
    }

    public function testValidateThrowsWhenTarifIsNegative(): void
{
    $e = $this->createValidEvenement();
    $e->setTarif(-1);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Le tarif ne peut pas etre negatif.');

    $this->manager->validate($e);
}

public function testValidateThrowsWhenTarifIsTooHigh(): void
{
    $e = $this->createValidEvenement();
    $e->setTarif(10000.01);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Le tarif ne peut pas depasser 10000 TND.');

    $this->manager->validate($e);
}

public function testValidateThrowsWhenTitreIsTooShort(): void
{
    $e = $this->createValidEvenement();
    $e->setTitre('Abc');

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Le titre doit contenir au moins 5 caracteres.');

    $this->manager->validate($e);
}

public function testValidateThrowsWhenTitreIsTooLong(): void
{
    $e = $this->createValidEvenement();
    $e->setTitre(str_repeat('A', 201));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Le titre ne peut pas depasser 200 caracteres.');

    $this->manager->validate($e);
}
}
