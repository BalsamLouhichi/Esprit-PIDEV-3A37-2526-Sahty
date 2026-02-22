<?php

namespace App\Repository;

use App\Entity\LaboratoireTypeAnalyse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LaboratoireTypeAnalyse>
 */
class LaboratoireTypeAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LaboratoireTypeAnalyse::class);
    }


    // Dans LaboratoireTypeAnalyseRepository.php

/**
 * Calcule le prix moyen pour un type d'analyse
 */
public function getPrixMoyenForType(TypeAnalyse $typeAnalyse): ?float
{
    $result = $this->createQueryBuilder('lta')
        ->select('AVG(lta.prix)')
        ->where('lta.typeAnalyse = :type')
        ->andWhere('lta.disponible = :disponible')
        ->setParameter('type', $typeAnalyse)
        ->setParameter('disponible', true)
        ->getQuery()
        ->getSingleScalarResult();

    return $result ? round($result, 2) : null;
}

/**
 * Calcule le prix minimum pour un type d'analyse
 */
public function getPrixMinForType(TypeAnalyse $typeAnalyse): ?float
{
    $result = $this->createQueryBuilder('lta')
        ->select('MIN(lta.prix)')
        ->where('lta.typeAnalyse = :type')
        ->andWhere('lta.disponible = :disponible')
        ->setParameter('type', $typeAnalyse)
        ->setParameter('disponible', true)
        ->getQuery()
        ->getSingleScalarResult();

    return $result ? round($result, 2) : null;
}

/**
 * Calcule le prix maximum pour un type d'analyse
 */
public function getPrixMaxForType(TypeAnalyse $typeAnalyse): ?float
{
    $result = $this->createQueryBuilder('lta')
        ->select('MAX(lta.prix)')
        ->where('lta.typeAnalyse = :type')
        ->andWhere('lta.disponible = :disponible')
        ->setParameter('type', $typeAnalyse)
        ->setParameter('disponible', true)
        ->getQuery()
        ->getSingleScalarResult();

    return $result ? round($result, 2) : null;
}

//    /**
//     * @return LaboratoireTypeAnalyse[] Returns an array of LaboratoireTypeAnalyse objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('l.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?LaboratoireTypeAnalyse
//    {
//        return $this->createQueryBuilder('l')
//            ->andWhere('l.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
