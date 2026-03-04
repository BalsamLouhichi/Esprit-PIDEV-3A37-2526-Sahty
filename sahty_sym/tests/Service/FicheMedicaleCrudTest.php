<?php

namespace App\Tests\Service;

use App\Entity\FicheMedicale;
use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FicheMedicaleCrudTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    /** @var ObjectRepository<FicheMedicale> */
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
        $this->repository = $this->em->getRepository(FicheMedicale::class);
    }

    public function testCreateFicheMedicale(): void
    {
        $patient = $this->createPatient();

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        $fiche->setTaille('1.70');
        $fiche->setPoids('68.00');
        $fiche->calculerImc();

        $this->em->persist($fiche);
        $this->em->flush();

        $this->assertNotNull($fiche->getId());

        $saved = $this->repository->find($fiche->getId());
        $this->assertInstanceOf(FicheMedicale::class, $saved);
        $this->assertSame('1.70', $saved->getTaille());
        $this->assertSame('68.00', $saved->getPoids());
    }

    public function testReadFicheMedicaleById(): void
    {
        $patient = $this->createPatient();

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        $fiche->setAntecedents('Asthme');

        $this->em->persist($fiche);
        $this->em->flush();
        $id = $fiche->getId();

        $this->em->clear();

        $loaded = $this->repository->find($id);

        $this->assertInstanceOf(FicheMedicale::class, $loaded);
        $this->assertSame('Asthme', $loaded->getAntecedents());
    }

    public function testUpdateFicheMedicale(): void
    {
        $patient = $this->createPatient();

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        $fiche->setAntecedents('Aucun');

        $this->em->persist($fiche);
        $this->em->flush();
        $id = $fiche->getId();

        $fiche->setAntecedents('Hypertension');
        $fiche->setObservations('Suivi trimestriel');
        $this->em->flush();

        $this->em->clear();

        $updated = $this->repository->find($id);

        $this->assertInstanceOf(FicheMedicale::class, $updated);
        $this->assertSame('Hypertension', $updated->getAntecedents());
        $this->assertSame('Suivi trimestriel', $updated->getObservations());
    }

    public function testDeleteFicheMedicale(): void
    {
        $patient = $this->createPatient();

        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);

        $this->em->persist($fiche);
        $this->em->flush();
        $id = $fiche->getId();

        $this->em->remove($fiche);
        $this->em->flush();
        $this->em->clear();

        $deleted = $this->repository->find($id);

        $this->assertNull($deleted);
    }

    private function createPatient(): Patient
    {
        $patient = new Patient();
        $patient->setEmail(sprintf('fiche-crud-%s@example.test', uniqid('', true)));
        $patient->setNom('Doe');
        $patient->setPrenom('Jane');
        $patient->setPassword('hashed-password');

        $this->em->persist($patient);
        $this->em->flush();

        return $patient;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}