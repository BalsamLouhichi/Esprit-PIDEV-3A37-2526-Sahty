<?php

namespace App\Repository;

use App\Entity\Recommandation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

class RecommandationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recommandation::class);
    }

    public function findPaginated(int $page, int $limit): array
    {
        $query = $this->createQueryBuilder('r')
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($query);
        $total = count($paginator);
        $pages = ceil($total / $limit);

        return [
            'results' => $paginator,
            'current_page' => $page,
            'max_per_page' => $limit,
            'total_pages' => $pages,
            'total_items' => $total
        ];
    }

    public function findByFilters($search = null, $quizId = null, $minScore = null, $maxScore = null, $sort = 'id', $direction = 'desc', $page = 1, $limit = 10)
{
    $qb = $this->createQueryBuilder('r')
        ->leftJoin('r.quiz', 'q');

    // Recherche texte sur nom
    if ($search) {
        $qb->andWhere('r.name LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    // Filtre quiz
    if ($quizId) {
        $qb->andWhere('r.quiz = :quiz')
           ->setParameter('quiz', $quizId);
    }

    // Score min
    if ($minScore !== null) {
        $qb->andWhere('r.min_score >= :minScore')
           ->setParameter('minScore', $minScore);
    }

    // Score max
    if ($maxScore !== null) {
        $qb->andWhere('r.max_score <= :maxScore')
           ->setParameter('maxScore', $maxScore);
    }

    // Tri
    $qb->orderBy('r.' . $sort, strtoupper($direction));

    // Pagination
    $paginator = new Paginator($qb->getQuery());
    $paginator->getQuery()
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    return [
        'results' => $paginator,
        'current_page' => $page,
        'max_per_page' => $limit,
        'total_pages' => ceil(count($paginator) / $limit),
        'total_items' => count($paginator)
    ];
}
}