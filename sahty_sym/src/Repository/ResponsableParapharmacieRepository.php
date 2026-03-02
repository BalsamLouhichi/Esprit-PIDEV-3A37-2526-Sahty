<?php

namespace App\Repository;

use App\Entity\ResponsableParapharmacie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponsableParapharmacie>
 */
class ResponsableParapharmacieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponsableParapharmacie::class);
    }

    // Méthode pour trouver un responsable avec sa parapharmacie
    public function findWithParapharmacie(int $id): ?ResponsableParapharmacie
    {
        return $this->createQueryBuilder('rp')
            ->leftJoin('rp.parapharmacie', 'p')
            ->addSelect('p')
            ->where('rp.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
