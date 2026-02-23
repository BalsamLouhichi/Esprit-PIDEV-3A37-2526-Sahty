<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Trouver les commandes par parapharmacie
     */
    public function findByParapharmacie($parapharmacieId)
    {
        return $this->createQueryBuilder('c')
            ->join('c.parapharmacie', 'p')
            ->where('p.id = :parapharmacieId')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les commandes par produit
     */
    public function findByProduit($produitId)
    {
        return $this->createQueryBuilder('c')
            ->join('c.produit', 'p')
            ->where('p.id = :produitId')
            ->setParameter('produitId', $produitId)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les commandes par statut
     */
    public function findByStatut($statut)
    {
        return $this->createQueryBuilder('c')
            ->where('c.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des commandes
     */
    public function getStats()
    {
        return $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total_commandes',
                'SUM(c.quantite) as total_quantite',
                'SUM(c.prixTotal) as total_chiffre'
            ])
            ->where('c.statut != :annulee')
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Commandes récentes (7 derniers jours)
     */
    public function findRecentOrders($limit = 10)
    {
        $date = new \DateTime('-7 days');

        return $this->createQueryBuilder('c')
            ->where('c.dateCreation >= :date')
            ->setParameter('date', $date)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes par email
     */
    public function findByEmail($email)
    {
        return $this->createQueryBuilder('c')
            ->where('c.email = :email')
            ->setParameter('email', $email)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les commandes par parapharmacie et statut
     */
    public function findByParapharmacieAndStatut($parapharmacieId, $statut)
    {
        return $this->createQueryBuilder('c')
            ->join('c.parapharmacie', 'p')
            ->where('p.id = :parapharmacieId')
            ->andWhere('c.statut = :statut')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->setParameter('statut', $statut)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commandes récentes par parapharmacie
     */
    public function findRecentByParapharmacie($parapharmacieId, $limit = 10)
    {
        return $this->createQueryBuilder('c')
            ->join('c.parapharmacie', 'p')
            ->where('p.id = :parapharmacieId')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par parapharmacie
     */
    public function getStatsByParapharmacie($parapharmacieId)
    {
        return $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total_commandes',
                'SUM(c.quantite) as total_quantite',
                'SUM(c.prixTotal) as total_chiffre_affaires'
            ])
            ->join('c.parapharmacie', 'p')
            ->where('p.id = :parapharmacieId')
            ->andWhere('c.statut != :annulee')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Statistiques mensuelles par parapharmacie (version SQL native)
     */
    public function getMonthlyStatsByParapharmacie($parapharmacieId)
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = "
            SELECT 
                MONTH(c.date_creation) as mois,
                YEAR(c.date_creation) as annee,
                COUNT(c.id) as nb_commandes,
                SUM(c.prix_total) as chiffre_affaires
            FROM commande c
            INNER JOIN parapharmacie p ON c.parapharmacie_id = p.id
            WHERE p.id = :parapharmacieId
            AND c.statut != 'annulee'
            GROUP BY annee, mois
            ORDER BY annee DESC, mois DESC
            LIMIT 12
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('parapharmacieId', $parapharmacieId);
        $resultSet = $stmt->executeQuery();
        
        return $resultSet->fetchAllAssociative();
    }

    /**
     * Statistiques par statut pour une parapharmacie
     */
    public function getStatsByStatutAndParapharmacie($parapharmacieId)
    {
        return $this->createQueryBuilder('c')
            ->select(['c.statut', 'COUNT(c.id) as nb_commandes'])
            ->join('c.parapharmacie', 'p')
            ->where('p.id = :parapharmacieId')
            ->setParameter('parapharmacieId', $parapharmacieId)
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();
    }
}