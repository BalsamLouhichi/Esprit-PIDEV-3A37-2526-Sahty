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
     * Recherche semantique basique a partir d'une liste de mots-clés enrichis.
     *
     * @param string[] $keywords
     */
    public function semanticSearch(array $keywords, int $limit = 30): array
    {
        $keywords = array_values(array_filter(array_unique(array_map(
            static fn (string $k) => trim(mb_strtolower($k)),
            $keywords
        ))));

        if (empty($keywords)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p');
        $orX = $qb->expr()->orX();
        $scoreParts = [];

        foreach ($keywords as $i => $keyword) {
            $param = 'k' . $i;
            $like = '%' . $keyword . '%';

            $orX->add("LOWER(p.nom) LIKE :$param");
            $orX->add("LOWER(COALESCE(p.description, '')) LIKE :$param");
            $orX->add("LOWER(COALESCE(p.categorie, '')) LIKE :$param");
            $orX->add("LOWER(COALESCE(p.marque, '')) LIKE :$param");

            $scoreParts[] = "(CASE WHEN LOWER(p.nom) LIKE :$param THEN 6 ELSE 0 END"
                . " + CASE WHEN LOWER(COALESCE(p.categorie, '')) LIKE :$param THEN 4 ELSE 0 END"
                . " + CASE WHEN LOWER(COALESCE(p.marque, '')) LIKE :$param THEN 3 ELSE 0 END"
                . " + CASE WHEN LOWER(COALESCE(p.description, '')) LIKE :$param THEN 2 ELSE 0 END)";

            $qb->setParameter($param, $like);
        }

        $qb->where($orX);
        $qb->andWhere('(p.estActif = true OR p.estActif IS NULL)');
        $qb->addSelect(implode(' + ', $scoreParts) . ' AS HIDDEN relevance');
        $qb->orderBy('relevance', 'DESC');
        $qb->addOrderBy('p.nom', 'ASC');
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
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
     * @return Produit[]
     */
    public function findLowStockByParapharmacie(int $parapharmacieId, int $threshold = 5): array
    {
        $threshold = max(1, $threshold);

        return $this->createQueryBuilder('p')
            ->join('p.parapharmacies', 'ph')
            ->where('ph.id = :parapharmacieId')
            ->andWhere('p.stock IS NOT NULL')
            ->andWhere('p.stock < :threshold')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
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

    /**
     * Trouver les produits avec un nom equivalent (insensible a la casse et aux espaces en bordure)
     */
    public function findByNormalizedName(string $nom): array
    {
        return $this->createQueryBuilder('p')
            ->where('LOWER(TRIM(p.nom)) = LOWER(TRIM(:nom))')
            ->setParameter('nom', $nom)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     * @return Produit[]
     */
    public function findActiveByIdsOrdered(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if (empty($ids)) {
            return [];
        }

        $items = $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->andWhere('(p.estActif = true OR p.estActif IS NULL)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($items as $item) {
            $byId[(int) $item->getId()] = $item;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }
}
