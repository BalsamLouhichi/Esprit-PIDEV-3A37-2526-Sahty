<?php

namespace App\Repository;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    public function findLatestInProgressForUserAndQuiz(Utilisateur $user, Quiz $quiz): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.user = :user')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.status = :status')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->setParameter('status', 'in_progress')
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
