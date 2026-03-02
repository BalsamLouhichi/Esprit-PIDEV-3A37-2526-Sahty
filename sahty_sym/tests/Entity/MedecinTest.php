<?php

namespace App\Tests\Entity;

use App\Entity\DemandeAnalyse;
use App\Entity\Medecin;
use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class MedecinTest extends TestCase
{
    public function testDefaultRoleAndCollections(): void
    {
        $medecin = new Medecin();

        $this->assertSame(Utilisateur::ROLE_SIMPLE_MEDECIN, $medecin->getRole());
        $this->assertSame(Utilisateur::ROLE_MEDECIN, $medecin->getRoleSymfony());
        $this->assertSame([Utilisateur::ROLE_MEDECIN], $medecin->getRoles());
        $this->assertCount(0, $medecin->getDemandeAnalyses());
    }

    public function testNomCompletAvecSpecialite(): void
    {
        $medecin = new Medecin();
        $medecin->setPrenom('Leila');
        $medecin->setNom('Ben Salah');

        $this->assertSame('Leila Ben Salah', $medecin->getNomCompletAvecSpecialite());

        $medecin->setSpecialite('Cardiologie');

        $this->assertSame('Leila Ben Salah (Cardiologie)', $medecin->getNomCompletAvecSpecialite());
    }

    public function testDemandeAnalyseRelation(): void
    {
        $medecin = new Medecin();
        $demande = new DemandeAnalyse();

        $medecin->addDemandeAnalyse($demande);

        $this->assertTrue($medecin->getDemandeAnalyses()->contains($demande));
        $this->assertSame($medecin, $demande->getMedecin());

        $medecin->removeDemandeAnalyse($demande);

        $this->assertFalse($medecin->getDemandeAnalyses()->contains($demande));
        $this->assertNull($demande->getMedecin());
    }
}
