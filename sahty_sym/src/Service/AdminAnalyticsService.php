<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\FicheMedicale;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use App\Repository\DemandeAnalyseRepository;
use App\Repository\FicheMedicaleRepository;
use App\Repository\LaboratoireRepository;
use App\Repository\RendezVousRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminAnalyticsService
{
    private UtilisateurRepository $userRepo;
    private RendezVousRepository $rdvRepo;
    private DemandeAnalyseRepository $demandeRepo;
    private LaboratoireRepository $laboratoireRepo;
    private FicheMedicaleRepository $ficheRepo;
    private EntityManagerInterface $em;

    public function __construct(
        UtilisateurRepository $userRepo,
        RendezVousRepository $rdvRepo,
        DemandeAnalyseRepository $demandeRepo,
        LaboratoireRepository $laboratoireRepo,
        FicheMedicaleRepository $ficheRepo,
        EntityManagerInterface $em
    ) {
        $this->userRepo = $userRepo;
        $this->rdvRepo = $rdvRepo;
        $this->demandeRepo = $demandeRepo;
        $this->laboratoireRepo = $laboratoireRepo;
        $this->ficheRepo = $ficheRepo;
        $this->em = $em;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $until = $until ? \DateTimeImmutable::createFromInterface($until) : new \DateTimeImmutable();
        $since = $since ? \DateTimeImmutable::createFromInterface($since) : $until->modify('-30 days');

        $totalUsers = $this->userRepo->count([]);
        $totalMedecins = $this->userRepo->count(['role' => Utilisateur::ROLE_SIMPLE_MEDECIN]);
        $totalPatients = $this->userRepo->count(['role' => Utilisateur::ROLE_SIMPLE_PATIENT]);
        $totalResponsableLabo = $this->userRepo->count(['role' => Utilisateur::ROLE_SIMPLE_RESPONSABLE_LABO]);
        $totalResponsablePara = $this->userRepo->count(['role' => Utilisateur::ROLE_SIMPLE_RESPONSABLE_PARA]);
        $totalAdmins = $this->userRepo->count(['role' => Utilisateur::ROLE_SIMPLE_ADMIN]);
        $totalInactive = $this->userRepo->count(['estActif' => false]);

        $newUsers = (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(Utilisateur::class, 'u')
            ->where('u.creeLe >= :since')
            ->andWhere('u.creeLe <= :until')
            ->setParameter('since', $since)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();

        $totalLaboratoires = $this->laboratoireRepo->count([]);
        $availableLaboratoires = $this->laboratoireRepo->count(['disponible' => true]);

        $totalDemandes = $this->demandeRepo->count([]);
        $totalDemandesPeriod = (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(DemandeAnalyse::class, 'd')
            ->where('d.date_demande >= :since')
            ->andWhere('d.date_demande <= :until')
            ->setParameter('since', $since)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();

        $demandesByStatus = $this->groupCountByField(DemandeAnalyse::class, 'statut');
        $demandesByPriority = $this->groupCountByField(DemandeAnalyse::class, 'priorite');

        $demandesPdfCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(DemandeAnalyse::class, 'd')
            ->where('d.resultatPdf IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $totalRdv = $this->rdvRepo->count([]);
        $totalRdvPeriod = $this->rdvRepo->countByDateRange(\DateTime::createFromInterface($since), \DateTime::createFromInterface($until));

        $rdvByStatus = $this->groupCountByField(RendezVous::class, 'statut');

        $cancelStatuses = ['Annulé', 'Annule', 'annule', 'annulé'];
        $rdvCancelled30d = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(RendezVous::class, 'r')
            ->where('r.statut IN (:cancel)')
            ->andWhere('r.dateRdv >= :since')
            ->andWhere('r.dateRdv <= :until')
            ->setParameter('cancel', $cancelStatuses)
            ->setParameter('since', $since)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();
        $cancelRatePeriod = $totalRdvPeriod > 0 ? round(($rdvCancelled30d / $totalRdvPeriod) * 100, 2) : 0.0;

        $totalFiches = $this->ficheRepo->count([]);
        $fichesPeriod = (int) $this->em->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(FicheMedicale::class, 'f')
            ->where('f.creeLe >= :since')
            ->andWhere('f.creeLe <= :until')
            ->setParameter('since', $since)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();

        $topLabs = $this->em->createQueryBuilder()
            ->select('l.nom as nom, COUNT(d.id) as total')
            ->from(DemandeAnalyse::class, 'd')
            ->join('d.laboratoire', 'l')
            ->groupBy('l.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return [
            'generated_at' => $until->format('Y-m-d H:i:s'),
            'period' => [
                'start' => $since->format('Y-m-d'),
                'end' => $until->format('Y-m-d'),
            ],
            'users' => [
                'total' => $totalUsers,
                'new' => $newUsers,
                'active' => $totalUsers - $totalInactive,
                'inactive' => $totalInactive,
                'by_role' => [
                    'admin' => $totalAdmins,
                    'medecin' => $totalMedecins,
                    'patient' => $totalPatients,
                    'responsable_labo' => $totalResponsableLabo,
                    'responsable_para' => $totalResponsablePara,
                ],
            ],
            'laboratoires' => [
                'total' => $totalLaboratoires,
                'available' => $availableLaboratoires,
                'unavailable' => max(0, $totalLaboratoires - $availableLaboratoires),
                'top_by_demands' => $topLabs,
            ],
            'demandes_analyses' => [
                'total' => $totalDemandes,
                'period' => $totalDemandesPeriod,
                'by_status' => $demandesByStatus,
                'by_priority' => $demandesByPriority,
                'with_pdf' => $demandesPdfCount,
                'pdf_ratio' => $totalDemandes > 0 ? round(($demandesPdfCount / $totalDemandes) * 100, 2) : 0.0,
            ],
            'rendez_vous' => [
                'total' => $totalRdv,
                'period' => $totalRdvPeriod,
                'by_status' => $rdvByStatus,
                'cancel_rate_period' => $cancelRatePeriod,
            ],
            'fiches_medicales' => [
                'total' => $totalFiches,
                'period' => $fichesPeriod,
            ],
        ];
    }

    /**
     * @param class-string $entityClass
     * @return array<string, int>
     */
    private function groupCountByField(string $entityClass, string $field): array
    {
        $results = $this->em->createQueryBuilder()
            ->select(sprintf('e.%s as label, COUNT(e.id) as total', $field))
            ->from($entityClass, 'e')
            ->groupBy(sprintf('e.%s', $field))
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $label = $row['label'] ?? 'inconnu';
            $counts[(string) $label] = (int) $row['total'];
        }

        return $counts;
    }
}
