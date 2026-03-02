<?php

namespace App\Repository;

use App\Entity\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Quiz>
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    public function findPaginated(int $page, int $limit): array
    {
        $query = $this->createQueryBuilder('q')
            ->orderBy('q.id', 'DESC')
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
  public function findByFilters(
      ?string $search = null,
      ?int $minQuestions = null,
      string $sort = 'id',
      string $direction = 'desc',
      int $page = 1,
      int $limit = 6
  ): array
{
    $qb = $this->createQueryBuilder('q');

    // Recherche par nom
    if ($search) {
        $qb->andWhere('q.name LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    // Filtre nombre de questions minimum
    if ($minQuestions !== null) {
        $qb->andWhere('JSON_LENGTH(q.questions) >= :minQuestions')
           ->setParameter('minQuestions', $minQuestions);
    }

    // Tri
    if ($sort === 'questions_count') {
        $qb->orderBy('JSON_LENGTH(q.questions)', strtoupper($direction));
    } else {
        $qb->orderBy('q.' . $sort, strtoupper($direction));
    }

    // Pagination
    $query = $qb->getQuery();

    $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
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
