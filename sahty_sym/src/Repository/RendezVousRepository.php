<?php

namespace App\Repository;

use App\Entity\RendezVous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    /**
     * Trouve les rendez-vous entre deux dates
     * 
     * @param \DateTime|null $start Date de début
     * @param \DateTime|null $end Date de fin
     * @return RendezVous[] Tableau des rendez-vous
     */
    public function findByDateRange(?\DateTime $start, ?\DateTime $end): array
    {
        if (!$start || !$end) {
            return $this->findAll();
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les rendez-vous entre deux dates
     * 
     * @param \DateTime|null $start Date de début
     * @param \DateTime|null $end Date de fin
     * @return int Nombre de rendez-vous
     */
    public function countByDateRange(?\DateTime $start, ?\DateTime $end): int
    {
        if (!$start || !$end) {
            return $this->count([]);
        }

        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les rendez-vous par année
     * 
     * @param string $year Année (ex: '2024')
     * @return RendezVous[] Tableau des rendez-vous
     */
    public function findByYear(string $year): array
    {
        $start = new \DateTime($year . '-01-01 00:00:00');
        $end = new \DateTime($year . '-12-31 23:59:59');

        return $this->createQueryBuilder('r')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous d'un patient
     * 
     * @param int $patientId ID du patient
     * @return RendezVous[] Tableau des rendez-vous
     */
    public function findByPatient(int $patientId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.patient = :patientId')
            ->setParameter('patientId', $patientId)
            ->orderBy('r.dateRdv', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous d'un médecin
     * 
     * @param int $medecinId ID du médecin
     * @return RendezVous[] Tableau des rendez-vous
     */
    public function findByMedecin(int $medecinId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecinId')
            ->setParameter('medecinId', $medecinId)
            ->orderBy('r.dateRdv', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous par statut
     * 
     * @param string $statut Statut du rendez-vous
     * @return RendezVous[] Tableau des rendez-vous
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('r.dateRdv', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous à venir (date >= aujourd'hui)
     * 
     * @return RendezVous[] Tableau des rendez-vous à venir
     */
    public function findUpcoming(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        return $this->createQueryBuilder('r')
            ->andWhere('r.dateRdv >= :today')
            ->setParameter('today', $today)
            ->orderBy('r.dateRdv', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rendez-vous passés (date < aujourd'hui)
     * 
     * @return RendezVous[] Tableau des rendez-vous passés
     */
    public function findPast(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        return $this->createQueryBuilder('r')
            ->andWhere('r.dateRdv < :today')
            ->setParameter('today', $today)
            ->orderBy('r.dateRdv', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les rendez-vous par statut pour une période donnée
     * 
     * @param string $statut Statut du rendez-vous
     * @param \DateTime|null $start Date de début
     * @param \DateTime|null $end Date de fin
     * @return int Nombre de rendez-vous
     */
    public function countByStatutAndDateRange(string $statut, ?\DateTime $start = null, ?\DateTime $end = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.statut = :statut')
            ->setParameter('statut', $statut);

        if ($start && $end) {
            $qb->andWhere('r.dateRdv BETWEEN :start AND :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les statistiques mensuelles des rendez-vous
     * 
     * @param int $year Année
     * @return array Statistiques par mois
     */
    public function getMonthlyStats(int $year): array
    {
        $stats = array_fill(0, 12, 0);
        
        $start = new \DateTime($year . '-01-01 00:00:00');
        $end = new \DateTime($year . '-12-31 23:59:59');

        $results = $this->createQueryBuilder('r')
            ->select('MONTH(r.dateRdv) as month, COUNT(r.id) as count')
            ->andWhere('r.dateRdv BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('month')
            ->getQuery()
            ->getResult();

        foreach ($results as $result) {
            $stats[$result['month'] - 1] = (int)$result['count'];
        }

        return $stats;
    }

    /**
     * Récupère les statistiques par jour de la semaine
     * 
     * @param \DateTime|null $start Date de début
     * @param \DateTime|null $end Date de fin
     * @return array Statistiques par jour
     */
    public function getWeekdayStats(?\DateTime $start = null, ?\DateTime $end = null): array
    {
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $stats = array_fill(0, 7, 0);

        $qb = $this->createQueryBuilder('r')
            ->select('WEEKDAY(r.dateRdv) as weekday, COUNT(r.id) as count');

        if ($start && $end) {
            $qb->andWhere('r.dateRdv BETWEEN :start AND :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        }

        $results = $qb->groupBy('weekday')
            ->getQuery()
            ->getResult();

        foreach ($results as $result) {
            // WEEKDAY retourne 0 pour Lundi, 6 pour Dimanche
            $stats[$result['weekday']] = (int)$result['count'];
        }

        return $stats;
    }
}