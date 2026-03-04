<?php

namespace App\Tests\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\Laboratoire;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Exception\DemandeAnalyseNotFoundException;
use App\Repository\DemandeAnalyseRepository;
use App\Service\DemandeAnalyseManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DemandeAnalyseManagerTest extends TestCase
{
    private DemandeAnalyseRepository $repository;
    private EntityManagerInterface $entityManager;
    private DemandeAnalyseManager $manager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DemandeAnalyseRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->manager = new DemandeAnalyseManager($this->repository, $this->entityManager);
    }

    // Verifie que la creation d'une demande renseigne les champs,
    // puis appelle bien persist() et flush() sur l'EntityManager.
    public function testCreatePersistsAndFlushes(): void
    {
        $patient = new Patient();
        $laboratoire = new Laboratoire();
        $medecin = new Medecin();

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(DemandeAnalyse::class));
        $this->entityManager->expects($this->once())->method('flush');

        $demande = $this->manager->create(
            $patient,
            $laboratoire,
            'Bilan sanguin',
            ['glucose', 'cholesterol'],
            $medecin,
            'Urgente',
            'A jeun'
        );

        $this->assertSame($patient, $demande->getPatient());
        $this->assertSame($laboratoire, $demande->getLaboratoire());
        $this->assertSame($medecin, $demande->getMedecin());
        $this->assertSame('Bilan sanguin', $demande->getTypeBilan());
        $this->assertSame(['glucose', 'cholesterol'], $demande->getAnalyses());
        $this->assertSame('Urgente', $demande->getPriorite());
        $this->assertSame('A jeun', $demande->getNotes());
        $this->assertSame('en_attente', $demande->getStatut());
    }

    // Verifie que la programmation met a jour la date programmee
    // et positionne le statut sur "programmee".
    public function testProgrammeUpdatesFieldsAndFlushes(): void
    {
        $demande = new DemandeAnalyse();
        $dateProgramme = new \DateTimeImmutable('2026-03-10 09:30:00');

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->manager->programme($demande, $dateProgramme);

        $this->assertSame($demande, $result);
        $this->assertSame('programmee', $demande->getStatut());
        $this->assertSame($dateProgramme, $demande->getProgrammeLe());
    }

    // Verifie que l'envoi met une date d'envoi
    // et le statut "envoyee".
    public function testMarquerEnvoyeeSetsStatusAndEnvoyeLe(): void
    {
        $demande = new DemandeAnalyse();

        $this->entityManager->expects($this->once())->method('flush');

        $this->manager->marquerEnvoyee($demande);

        $this->assertSame('envoyee', $demande->getStatut());
        $this->assertInstanceOf(\DateTimeInterface::class, $demande->getEnvoyeLe());
    }

    // Verifie que l'attachement d'un PDF enregistre le chemin
    // et met le statut "resultat_disponible".
    public function testAttacherResultatPdfSetsStatusAndPath(): void
    {
        $demande = new DemandeAnalyse();
        $pdfPath = '/uploads/resultats/r1.pdf';

        $this->entityManager->expects($this->once())->method('flush');

        $this->manager->attacherResultatPdf($demande, $pdfPath);

        $this->assertSame('resultat_disponible', $demande->getStatut());
        $this->assertSame($pdfPath, $demande->getResultatPdf());
    }

    // Verifie que findOrFail() retourne l'entite
    // quand le repository la trouve.
    public function testFindOrFailReturnsEntityWhenFound(): void
    {
        $demande = new DemandeAnalyse();

        $this->repository->expects($this->once())
            ->method('find')
            ->with(12)
            ->willReturn($demande);

        $this->assertSame($demande, $this->manager->findOrFail(12));
    }

    // Verifie que findOrFail() lance une exception dediee
    // quand aucune demande n'est trouvee.
    public function testFindOrFailThrowsWhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('find')
            ->with(404)
            ->willReturn(null);

        $this->expectException(DemandeAnalyseNotFoundException::class);
        $this->expectExceptionMessage('DemandeAnalyse #404 introuvable.');

        $this->manager->findOrFail(404);
    }
}
