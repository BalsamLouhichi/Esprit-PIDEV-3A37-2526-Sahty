<?php

namespace App\Repository;

use App\Entity\Laboratoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Laboratoire>
 */
class LaboratoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Laboratoire::class);
    }

    /**
     * Trouve les laboratoires avec filtres et pagination
     */
    /**
     * @return Paginator<Laboratoire>
     */
    public function findWithFilters(
        ?string $search = null,
        ?string $ville = null,
        ?bool $disponible = null,
        int $page = 1,
        int $limit = 10
    ): Paginator {
        $queryBuilder = $this->createQueryBuilder('l');

        // Appliquer les filtres
        if ($search) {
            $queryBuilder->andWhere('l.nom LIKE :search OR l.ville LIKE :search OR l.adresse LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($ville) {
            $queryBuilder->andWhere('l.ville = :ville')
                ->setParameter('ville', $ville);
        }

        if ($disponible !== null) {
            $queryBuilder->andWhere('l.disponible = :disponible')
                ->setParameter('disponible', $disponible);
        }

        // Tri par nom
        $queryBuilder->orderBy('l.nom', 'ASC');

        // Pagination
        $query = $queryBuilder->getQuery();
        $paginator = new Paginator($query);
        $paginator->getQuery()
            ->setFirstResult($limit * ($page - 1))
            ->setMaxResults($limit);

        return $paginator;
    }

    /**
     * Trouve les villes distinctes des laboratoires
     */
    public function findDistinctVilles(int $limit = 80): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('DISTINCT l.ville')
            ->orderBy('l.ville', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'ville');
    }

    /**
     * Trouve les types de bilan distincts associes aux laboratoires
     */
    public function findDistinctTypeBilans(int $limit = 80): array
    {
        $result = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('DISTINCT ta.nom AS nom')
            ->from('App\Entity\TypeAnalyse', 'ta')
            ->where('ta.nom IS NOT NULL')
            ->andWhere(
                'EXISTS (
                    SELECT 1
                    FROM App\Entity\LaboratoireTypeAnalyse lta
                    JOIN lta.laboratoire l
                    WHERE lta.typeAnalyse = ta
                )'
            )
            ->orderBy('ta.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'nom');
    }

    /**
     * Filtre par nom, ville et type de bilan
     */
    public function findWithPublicFilters(
        ?string $name,
        ?string $ville,
        ?string $typeBilan,
        int $limit = 60
    ): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('partial l.{id,nom,ville,adresse,description,disponible}')
            ->orderBy('l.nom', 'ASC');

        if ($name) {
            $queryBuilder->andWhere('l.nom LIKE :name')
                ->setParameter('name', '%' . $name . '%');
        }

        if ($ville) {
            $queryBuilder->andWhere('l.ville = :ville')
                ->setParameter('ville', $ville);
        }

        if ($typeBilan) {
            $queryBuilder
                ->andWhere(
                    'EXISTS (
                        SELECT 1
                        FROM App\Entity\LaboratoireTypeAnalyse lta_filter
                        JOIN lta_filter.typeAnalyse ta_filter
                        WHERE lta_filter.laboratoire = l
                          AND ta_filter.nom = :typeBilan
                    )'
                )
                ->setParameter('typeBilan', $typeBilan);
        }

        $query = $queryBuilder
            ->distinct()
            ->setMaxResults($limit)
            ->getQuery();

        $query->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);

        return $query->getResult();
    }

    /**
     * Recherche pour l'API
     */
    public function findForApi(?string $search = null, ?string $ville = null, bool $disponible = true): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->where('l.disponible = :disponible')
            ->setParameter('disponible', $disponible);

        if ($search) {
            $queryBuilder->andWhere('l.nom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($ville) {
            $queryBuilder->andWhere('l.ville = :ville')
                ->setParameter('ville', $ville);
        }

        return $queryBuilder->orderBy('l.nom', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les laboratoires par ville
     */
    public function findByVille(string $ville): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.ville = :ville')
            ->andWhere('l.disponible = true')
            ->setParameter('ville', $ville)
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recommande les laboratoires les plus proches selon la ville du patient.
     * Priorite:
     * 1) Meme ville
     * 2) Autres villes (ordre alphabetique)
     */
    public function findRecommendedForVille(string $ville, int $limit = 6): array
    {
        $query = $this->createQueryBuilder('l')
            ->select('partial l.{id,nom,ville,adresse,description,disponible}')
            ->andWhere('l.disponible = :disponible')
            ->setParameter('disponible', true)
            ->addOrderBy('CASE WHEN LOWER(l.ville) = LOWER(:ville) THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('l.ville', 'ASC')
            ->addOrderBy('l.nom', 'ASC')
            ->setParameter('ville', trim($ville))
            ->setMaxResults($limit)
            ->getQuery();

        $query->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true);

        return $query->getResult();
    }

    /**
     * Trouve les laboratoires disponibles
     */
    public function findDisponibles(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.disponible = :disponible')
            ->setParameter('disponible', true)
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les laboratoires sans responsable
     */
    public function findSansResponsable(): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.responsable', 'r')
            ->where('r.id IS NULL')
            ->andWhere('l.disponible = true')
            ->orderBy('l.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par nom
     */
    public function searchByName(string $term): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.nom LIKE :term OR l.ville LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('l.nom', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}
