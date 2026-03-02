<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\Laboratoire;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Exception\DemandeAnalyseNotFoundException;
use App\Repository\DemandeAnalyseRepository;
use Doctrine\ORM\EntityManagerInterface;

class DemandeAnalyseManager
{
    public function __construct(
        private readonly DemandeAnalyseRepository $repository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param list<string> $analyses
     */
    public function create(
        Patient $patient,
        Laboratoire $laboratoire,
        string $typeBilan,
        array $analyses = [],
        ?Medecin $medecin = null,
        string $priorite = 'Normale',
        ?string $notes = null
    ): DemandeAnalyse {
        $demande = new DemandeAnalyse();
        $demande
            ->setPatient($patient)
            ->setLaboratoire($laboratoire)
            ->setMedecin($medecin)
            ->setTypeBilan($typeBilan)
            ->setAnalyses($analyses)
            ->setPriorite($priorite)
            ->setNotes($notes)
            ->setStatut('en_attente');

        $this->entityManager->persist($demande);
        $this->entityManager->flush();

        return $demande;
    }

    public function programme(DemandeAnalyse $demande, \DateTimeInterface $dateProgramme): DemandeAnalyse
    {
        $demande
            ->setProgrammeLe($dateProgramme)
            ->setStatut('programmee');

        $this->entityManager->flush();

        return $demande;
    }

    public function marquerEnvoyee(DemandeAnalyse $demande): DemandeAnalyse
    {
        $demande
            ->setEnvoyeLe(new \DateTimeImmutable())
            ->setStatut('envoyee');

        $this->entityManager->flush();

        return $demande;
    }

    public function attacherResultatPdf(DemandeAnalyse $demande, string $pdfPath): DemandeAnalyse
    {
        $demande
            ->setResultatPdf($pdfPath)
            ->setStatut('resultat_disponible');

        $this->entityManager->flush();

        return $demande;
    }

    public function findOrFail(int $id): DemandeAnalyse
    {
        $demande = $this->repository->find($id);

        if (!$demande) {
            throw DemandeAnalyseNotFoundException::forId($id);
        }

        return $demande;
    }
}
