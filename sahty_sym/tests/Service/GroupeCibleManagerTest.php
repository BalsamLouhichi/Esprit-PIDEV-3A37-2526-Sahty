<?php

namespace App\Tests\Service;

use App\Entity\GroupeCible;
use App\Service\GroupeCibleManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GroupeCibleManagerTest extends TestCase
{
    private GroupeCibleManager $manager;

    protected function setUp(): void
    {
        $this->manager = new GroupeCibleManager();
    }

    public function testValidateThrowsExceptionWhenNomIsEmpty(): void
    {
        $groupe = new GroupeCible();
        $groupe->setNom('');
        $groupe->setType('patient');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom du groupe cible est obligatoire.');

        $this->manager->validate($groupe);
    }

    public function testValidateThrowsExceptionWhenTypeIsInvalid(): void
    {
        $groupe = new GroupeCible();
        $groupe->setNom('Groupe cardio');
        $groupe->setType('autre');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type du groupe cible est invalide.');

        $this->manager->validate($groupe);
    }

    public function testValidateThrowsExceptionWhenCritereOptionnelIsTooLong(): void
    {
        $groupe = new GroupeCible();
        $groupe->setNom('Groupe prevention');
        $groupe->setType('patient');
        $groupe->setCritereOptionnel(str_repeat('a', 256));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le critere optionnel ne doit pas depasser 255 caracteres.');

        $this->manager->validate($groupe);
    }

    public function testValidateReturnsTrueForValidGroupeCible(): void
    {
        $groupe = new GroupeCible();
        $groupe->setNom('Groupe diabetologie');
        $groupe->setType('medecin');
        $groupe->setCritereOptionnel('Suivi patients chroniques');

        self::assertTrue($this->manager->validate($groupe));
    }
}
