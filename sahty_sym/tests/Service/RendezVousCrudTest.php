<?php

namespace App\Tests\Service;

use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RendezVousCrudTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    /** @var ObjectRepository<RendezVous> */
    private ObjectRepository $repository;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        if (!isset($_ENV['DATABASE_URL']) && getenv('DATABASE_URL') === false) {
            $this->markTestSkipped('DATABASE_URL non configuree pour les tests CRUD Doctrine.');
        }

        self::bootKernel();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            self::fail('EntityManagerInterface non disponible dans le conteneur.');
        }

        $this->em = $em;
        $this->repository = $this->em->getRepository(RendezVous::class);
    }

    public function testCreateRendezVous(): void
    {
        $medecin = $this->createMedecin();
        $patient = $this->createPatient();

        $rdv = new RendezVous();
        $rdv->setMedecin($medecin);
        $rdv->setPatient($patient);
        $rdv->setRaison('Consultation de routine');

        $this->em->persist($rdv);
        $this->em->flush();

        $this->assertNotNull($rdv->getId());

        $saved = $this->repository->find($rdv->getId());
        $this->assertInstanceOf(RendezVous::class, $saved);
        $this->assertSame('Consultation de routine', $saved->getRaison());
        $this->assertSame('en_attente', $saved->getStatut());
    }

    public function testReadRendezVousById(): void
    {
        $rdv = $this->createRendezVousEntity();
        $id = $rdv->getId();

        $this->em->clear();

        $loaded = $this->repository->find($id);

        $this->assertInstanceOf(RendezVous::class, $loaded);
        $this->assertSame('Consultation initiale', $loaded->getRaison());
    }

    public function testUpdateRendezVous(): void
    {
        $rdv = $this->createRendezVousEntity();
        $id = $rdv->getId();

        $rdv->setRaison('Controle post-traitement');
        $rdv->setStatut('valide');
        $this->em->flush();

        $this->em->clear();

        $updated = $this->repository->find($id);

        $this->assertInstanceOf(RendezVous::class, $updated);
        $this->assertSame('Controle post-traitement', $updated->getRaison());
        $this->assertSame('valide', $updated->getStatut());
    }

    public function testDeleteRendezVous(): void
    {
        $rdv = $this->createRendezVousEntity();
        $id = $rdv->getId();

        $this->em->remove($rdv);
        $this->em->flush();
        $this->em->clear();

        $deleted = $this->repository->find($id);

        $this->assertNull($deleted);
    }

    private function createRendezVousEntity(): RendezVous
    {
        $medecin = $this->createMedecin();
        $patient = $this->createPatient();

        $rdv = new RendezVous();
        $rdv->setMedecin($medecin);
        $rdv->setPatient($patient);
        $rdv->setRaison('Consultation initiale');

        $this->em->persist($rdv);
        $this->em->flush();

        return $rdv;
    }

    private function createPatient(): Patient
    {
        $patient = new Patient();
        $patient->setEmail(sprintf('rdv-patient-%s@example.test', uniqid('', true)));
        $patient->setNom('Patient');
        $patient->setPrenom('Test');
        $patient->setPassword('hashed-password');

        $this->em->persist($patient);
        $this->em->flush();

        return $patient;
    }

    private function createMedecin(): Medecin
    {
        $medecin = new Medecin();
        $medecin->setEmail(sprintf('rdv-medecin-%s@example.test', uniqid('', true)));
        $medecin->setNom('Medecin');
        $medecin->setPrenom('Test');
        $medecin->setPassword('hashed-password');

        $this->em->persist($medecin);
        $this->em->flush();

        return $medecin;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}