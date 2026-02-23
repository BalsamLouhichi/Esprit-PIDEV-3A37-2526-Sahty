<?php
// src/Repository/ParapharmacieRepository.php

namespace App\Repository;

use App\Entity\Produit;
use App\Entity\Parapharmacie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parapharmacie>
 */
class ParapharmacieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parapharmacie::class);
    }
    
    /**
     * Trouver toutes les parapharmacies qui ont un produit avec son prix
     */
    public function findAllWithProductAndPrice(Produit $produit)
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.produits', 'prod')
            ->where('prod.id = :produitId')
            ->setParameter('produitId', $produit->getId())
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}