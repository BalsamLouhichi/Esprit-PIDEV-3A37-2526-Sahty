<?php

namespace App\Repository;

use App\Entity\DemandeAnalyse;
use App\Entity\ResultatAnalyse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResultatAnalyse>
 */
class ResultatAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResultatAnalyse::class);
    }

    public function findOneByDemandeAnalyse(DemandeAnalyse $demandeAnalyse): ?ResultatAnalyse
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.demandeAnalyse = :demande')
            ->setParameter('demande', $demandeAnalyse)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
