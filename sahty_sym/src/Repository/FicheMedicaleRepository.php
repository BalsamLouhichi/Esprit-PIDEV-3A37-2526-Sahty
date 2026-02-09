<?php

namespace App\Repository;
use App\Entity\Patient;
use App\Entity\Medecin;
use App\Entity\FicheMedicale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FicheMedicale>
 */
class FicheMedicaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMedicale::class);
    }

    public function searchWithPermissions(string $query, $user): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')
            ->addSelect('p');

        // Conditions de recherche
        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->like('LOWER(p.nom)', ':query'),
                $qb->expr()->like('LOWER(p.prenom)', ':query'),
                $qb->expr()->like('LOWER(f.diagnostic)', ':query'),
                $qb->expr()->like('LOWER(f.statut)', ':query'),
                $qb->expr()->like('CAST(f.id AS string)', ':query')
            )
        )->setParameter('query', '%' . strtolower($query) . '%');

        // Filtrer selon le rôle
        if ($user instanceof Patient) {
            $qb->andWhere('f.patient = :patient')
               ->setParameter('patient', $user);
        } elseif ($user instanceof Medecin) {
            $qb->innerJoin('p.rendezVous', 'r')
               ->andWhere('r.medecin = :medecin')
               ->setParameter('medecin', $user);
        }

        return $qb->orderBy('f.creeLe', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les fiches selon le rôle de l'utilisateur
     */
    public function findByUserRole($user): array
    {
        if ($user instanceof Patient) {
            return $this->findBy(['patient' => $user], ['creeLe' => 'DESC']);
        } elseif ($user instanceof Medecin) {
            return $this->createQueryBuilder('f')
                ->innerJoin('f.patient', 'p')
                ->innerJoin('p.rendezVous', 'r')
                ->where('r.medecin = :medecin')
                ->setParameter('medecin', $user)
                ->orderBy('f.creeLe', 'DESC')
                ->getQuery()
                ->getResult();
        }
        
        // Admin ou autre : toutes les fiches
        return $this->findBy([], ['creeLe' => 'DESC']);
    }

    /**
     * Recherche textuelle avancée
     */
    public function searchByText(string $searchTerm, $user): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.patient', 'p')
            ->addSelect('p');

        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->like('LOWER(p.nom)', ':search'),
                $qb->expr()->like('LOWER(p.prenom)', ':search'),
                $qb->expr()->like('LOWER(f.antecedents)', ':search'),
                $qb->expr()->like('LOWER(f.allergies)', ':search'),
                $qb->expr()->like('LOWER(f.diagnostic)', ':search'),
                $qb->expr()->like('CAST(f.id AS string)', ':search')
            )
        )->setParameter('search', '%' . strtolower($searchTerm) . '%');

        // Filtrer selon le rôle
        if ($user instanceof Patient) {
            $qb->andWhere('f.patient = :patient')
               ->setParameter('patient', $user);
        } elseif ($user instanceof Medecin) {
            $qb->innerJoin('p.rendezVous', 'r')
               ->andWhere('r.medecin = :medecin')
               ->setParameter('medecin', $user);
        }

        return $qb->orderBy('f.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les fiches par statut
     */
    public function countByStatut(string $statut, $user): int
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.statut = :statut')
            ->setParameter('statut', $statut);

        if ($user instanceof Patient) {
            $qb->andWhere('f.patient = :patient')
               ->setParameter('patient', $user);
        } elseif ($user instanceof Medecin) {
            $qb->innerJoin('f.patient', 'p')
               ->innerJoin('p.rendezVous', 'r')
               ->andWhere('r.medecin = :medecin')
               ->setParameter('medecin', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Récupère les fiches récentes (moins de 30 jours)
     */
    public function findRecent($user, int $limit = 5): array
    {
        $date = new \DateTime('-30 days');
        
        $qb = $this->createQueryBuilder('f')
            ->where('f.creeLe >= :date')
            ->setParameter('date', $date);

        if ($user instanceof Patient) {
            $qb->andWhere('f.patient = :patient')
               ->setParameter('patient', $user);
        } elseif ($user instanceof Medecin) {
            $qb->innerJoin('f.patient', 'p')
               ->innerJoin('p.rendezVous', 'r')
               ->andWhere('r.medecin = :medecin')
               ->setParameter('medecin', $user);
        }

        return $qb->orderBy('f.creeLe', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
//    /**
//     * @return FicheMedicale[] Returns an array of FicheMedicale objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('f.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?FicheMedicale
//    {
//        return $this->createQueryBuilder('f')
//            ->andWhere('f.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
