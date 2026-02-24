<?php

namespace App\Repository;

use App\Entity\Medecin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Medecin>
 */

class MedecinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Medecin::class);
    }

    /**
     * Récupère tous les médecins triés par nom complet
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les médecins actifs (avec disponibilité)
     */
    public function findAvailableMedecins(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.disponibilite IS NOT NULL')
            ->orderBy('m.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de médecins par nom ou spécialité
     */
    public function searchMedecins(string $searchTerm): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.nomComplet LIKE :search')
            ->orWhere('m.specialite LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('m.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les médecins avec leurs informations essentielles
     */
    public function findAllWithEssentialInfo(): array
    {
        return $this->createQueryBuilder('m')
            ->select('m.id', 'm.nomComplet', 'm.specialite', 'm.telephoneCabinet')
            ->orderBy('m.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

    //    /**
    //     * @return Medecin[] Returns an array of Medecin objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Medecin
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
