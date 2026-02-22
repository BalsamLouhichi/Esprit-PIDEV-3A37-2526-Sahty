<?php

namespace App\Repository;

use App\Entity\Laboratoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

class LaboratoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Laboratoire::class);
    }

    /**
     * Trouve les laboratoires avec filtres et pagination
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
    public function findDistinctVilles(): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('DISTINCT l.ville')
            ->orderBy('l.ville', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'ville');
    }

    /**
     * Trouve les types de bilan distincts associes aux laboratoires
     */
    public function findDistinctTypeBilans(): array
    {
        $result = $this->createQueryBuilder('l')
            ->leftJoin('l.laboratoireTypeAnalyses', 'lta')
            ->leftJoin('lta.typeAnalyse', 'ta')
            ->select('DISTINCT ta.nom')
            ->where('ta.nom IS NOT NULL')
            ->orderBy('ta.nom', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'nom');
    }

    /**
     * Filtre par nom, ville et type de bilan
     */
    public function findWithPublicFilters(?string $name, ?string $ville, ?string $typeBilan): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->leftJoin('l.laboratoireTypeAnalyses', 'lta')
            ->leftJoin('lta.typeAnalyse', 'ta')
            ->addSelect('lta', 'ta')
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
            $queryBuilder->andWhere('ta.nom = :typeBilan')
                ->setParameter('typeBilan', $typeBilan);
        }

        return $queryBuilder->getQuery()->getResult();
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