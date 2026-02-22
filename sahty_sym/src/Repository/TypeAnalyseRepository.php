<?php

namespace App\Repository;

use App\Entity\TypeAnalyse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeAnalyse>
 */
class TypeAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeAnalyse::class);
    }

    //    /**
    //     * @return TypeAnalyse[] Returns an array of TypeAnalyse objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?TypeAnalyse
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

     public function findByCategorie($categorie)
    {
        return $this->createQueryBuilder('t')
            ->where('t.categorie = :categorie')
            ->andWhere('t.actif = :actif')
            ->setParameter('categorie', $categorie)
            ->setParameter('actif', true)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllActifsGroupedByCategorie()
    {
        $types = $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.categorie', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($types as $type) {
            $categorie = $type->getCategorie() ?? 'Autres';
            $grouped[$categorie][] = $type;
        }

        return $grouped;
    }
}
