<?php
// src/Repository/ProduitRepository.php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Rechercher des produits par terme
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nom LIKE :term OR p.description LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les produits par catégorie
     */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.categorie = :categorie')
            ->setParameter('categorie', $categorie)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les produits en promotion
     */
    public function findPromotions(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.promotion IS NOT NULL')
            ->andWhere('p.promotion > 0')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les produits par parapharmacie
     */
    public function findByParapharmacie($parapharmacieId)
    {
        return $this->createQueryBuilder('p')
            ->join('p.parapharmacies', 'ph')
            ->where('ph.id = :parapharmacieId')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les produits d'une parapharmacie
     */
    public function countByParapharmacie($parapharmacieId)
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.parapharmacies', 'ph')
            ->where('ph.id = :parapharmacieId')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->getQuery()
            ->getSingleScalarResult();
    }

   /**
 * Produits les plus vendus par parapharmacie
 */
public function findTopSellingByParapharmacie($parapharmacieId, $limit = 10)
{
    $conn = $this->getEntityManager()->getConnection();
    
    $sql = "
        SELECT 
            p.id,
            p.nom,
            p.prix,
            p.image,
            COALESCE(SUM(c.quantite), 0) as total_vendu
        FROM produit p
        INNER JOIN produit_parapharmacie pp ON p.id = pp.produit_id
        LEFT JOIN commande c ON p.id = c.produit_id AND c.statut != 'annulee'
        WHERE pp.parapharmacie_id = :parapharmacieId
        GROUP BY p.id, p.nom, p.prix, p.image
        ORDER BY total_vendu DESC
        LIMIT " . (int)$limit . "
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue('parapharmacieId', $parapharmacieId);
    $resultSet = $stmt->executeQuery();
    
    return $resultSet->fetchAllAssociative();
}
    /**
     * Recherche avancée de produits par parapharmacie
     */
    public function searchByParapharmacie($parapharmacieId, $searchTerm)
    {
        return $this->createQueryBuilder('p')
            ->join('p.parapharmacies', 'ph')
            ->where('ph.id = :parapharmacieId')
            ->andWhere('p.nom LIKE :search OR p.description LIKE :search OR p.marque LIKE :search')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}