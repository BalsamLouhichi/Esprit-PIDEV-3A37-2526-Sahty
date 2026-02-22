<?php

namespace App\Repository;

use App\Entity\DemandeAnalyse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeAnalyse>
 */
class DemandeAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeAnalyse::class);
    }


    // Dans DemandeAnalyseRepository.php

/**
 * Compte les demandes par plage de dates
 */
public function countByDateRange(?\DateTime $debut, ?\DateTime $fin): int
{
    $qb = $this->createQueryBuilder('d')
        ->select('COUNT(d.id)');

    if ($debut) {
        $qb->andWhere('d.date_demande >= :debut')
           ->setParameter('debut', $debut);
    }

    if ($fin) {
        $qb->andWhere('d.date_demande <= :fin')
           ->setParameter('fin', $fin);
    }

    return $qb->getQuery()->getSingleScalarResult();
}

/**
 * Compte les demandes par plage de dates et statut
 */
public function countByDateRangeAndStatut(?\DateTime $debut, ?\DateTime $fin, string $statut): int
{
    $qb = $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->where('d.statut = :statut')
        ->setParameter('statut', $statut);

    if ($debut) {
        $qb->andWhere('d.date_demande >= :debut')
           ->setParameter('debut', $debut);
    }

    if ($fin) {
        $qb->andWhere('d.date_demande <= :fin')
           ->setParameter('fin', $fin);
    }

    return $qb->getQuery()->getSingleScalarResult();
}

/**
 * Compte les demandes par statut et priorité
 */
public function countByStatutAndPriorite(string $statut, string $priorite): int
{
    return $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->where('d.statut = :statut')
        ->andWhere('d.priorite = :priorite')
        ->setParameter('statut', $statut)
        ->setParameter('priorite', $priorite)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compte les demandes par laboratoire et plage de dates
 */
public function countByLaboratoireAndDateRange(Laboratoire $laboratoire, \DateTime $debut, \DateTime $fin): int
{
    return $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->where('d.laboratoire = :laboratoire')
        ->andWhere('d.date_demande BETWEEN :debut AND :fin')
        ->setParameter('laboratoire', $laboratoire)
        ->setParameter('debut', $debut)
        ->setParameter('fin', $fin)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compte les demandes par type d'analyse
 */
public function countByTypeAnalyse(TypeAnalyse $typeAnalyse): int
{
    // À adapter selon comment vous stockez le type d'analyse dans DemandeAnalyse
    return $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->where('d.type_bilan = :type')
        ->setParameter('type', $typeAnalyse->getNom())
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compte les demandes par type d'analyse et laboratoire
 */
public function countByTypeAnalyseAndLaboratoire(TypeAnalyse $typeAnalyse, Laboratoire $laboratoire): int
{
    return $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->where('d.type_bilan = :type')
        ->andWhere('d.laboratoire = :laboratoire')
        ->setParameter('type', $typeAnalyse->getNom())
        ->setParameter('laboratoire', $laboratoire)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Compte les demandes par type d'analyse et plage de dates
 */
public function countByTypeAnalyseAndDateRange(TypeAnalyse $typeAnalyse, \DateTime $debut, \DateTime $fin): int
{
    return $this->createQueryBuilder('d')
        ->select('COUNT(d.id)')
        ->where('d.type_bilan = :type')
        ->andWhere('d.date_demande BETWEEN :debut AND :fin')
        ->setParameter('type', $typeAnalyse->getNom())
        ->setParameter('debut', $debut)
        ->setParameter('fin', $fin)
        ->getQuery()
        ->getSingleScalarResult();
}

/**
 * Trouve les demandes par plage de dates
 */
public function findByDateRange(?\DateTime $debut, ?\DateTime $fin): array
{
    $qb = $this->createQueryBuilder('d');

    if ($debut) {
        $qb->andWhere('d.date_demande >= :debut')
           ->setParameter('debut', $debut);
    }

    if ($fin) {
        $qb->andWhere('d.date_demande <= :fin')
           ->setParameter('fin', $fin);
    }

    return $qb->orderBy('d.date_demande', 'DESC')
              ->getQuery()
              ->getResult();
}

/**
 * Compte les demandes avec filtres multiples
 */
public function countByFilters(
    ?\DateTime $debut,
    ?\DateTime $fin,
    ?int $laboratoireId = null,
    ?int $typeAnalyseId = null
): int {
    $qb = $this->createQueryBuilder('d')
        ->select('COUNT(d.id)');

    if ($debut) {
        $qb->andWhere('d.date_demande >= :debut')
           ->setParameter('debut', $debut);
    }

    if ($fin) {
        $qb->andWhere('d.date_demande <= :fin')
           ->setParameter('fin', $fin);
    }

    if ($laboratoireId) {
        $qb->andWhere('d.laboratoire = :laboratoire')
           ->setParameter('laboratoire', $laboratoireId);
    }

    if ($typeAnalyseId) {
        $qb->andWhere('d.type_bilan = :type')
           ->setParameter('type', $typeAnalyseId); // À adapter
    }

    return $qb->getQuery()->getSingleScalarResult();
}

    //    /**
    //     * @return DemandeAnalyse[] Returns an array of DemandeAnalyse objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DemandeAnalyse
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
