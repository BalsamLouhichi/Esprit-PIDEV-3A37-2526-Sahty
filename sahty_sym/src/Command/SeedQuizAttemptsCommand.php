<?php

namespace App\Command;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\Utilisateur;
use App\Repository\QuizRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-quiz-attempts',
    description: 'Generate quiz attempt records for analytics testing',
)]
class SeedQuizAttemptsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuizRepository $quizRepository,
        private readonly UtilisateurRepository $utilisateurRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('per-quiz', null, InputOption::VALUE_REQUIRED, 'Attempts per quiz', '20')
            ->addOption('quiz-id', null, InputOption::VALUE_REQUIRED, 'Only seed one quiz ID')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Delete existing quiz_attempt rows before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $perQuiz = max(1, (int) $input->getOption('per-quiz'));
        $quizId = $input->getOption('quiz-id');
        $clear = (bool) $input->getOption('clear');

        if ($clear) {
            $this->em->getConnection()->executeStatement('DELETE FROM quiz_attempt');
            $io->warning('Existing quiz_attempt rows deleted.');
        }

        $quizzes = [];
        if ($quizId !== null && $quizId !== '') {
            $quiz = $this->quizRepository->find((int) $quizId);
            if (!$quiz instanceof Quiz) {
                $io->error('Quiz not found for ID ' . $quizId);
                return Command::FAILURE;
            }
            $quizzes = [$quiz];
        } else {
            $quizzes = $this->quizRepository->findAll();
        }

        if (count($quizzes) === 0) {
            $io->warning('No quizzes found.');
            return Command::SUCCESS;
        }

        $users = $this->utilisateurRepository->findAll();
        $userPool = array_values(array_filter($users, fn ($u) => $u instanceof Utilisateur));

        $created = 0;
        $now = new \DateTime();

        foreach ($quizzes as $quiz) {
            $questionIds = [];
            foreach ($quiz->getQuestions() as $question) {
                if ($question->getId() !== null) {
                    $questionIds[] = (string) $question->getId();
                }
            }

            $questionCount = count($questionIds);
            if ($questionCount === 0) {
                continue;
            }

            $maxScore = $questionCount * 5;
            for ($i = 0; $i < $perQuiz; $i++) {
                $attempt = new QuizAttempt();
                $attempt->setQuiz($quiz);
                $attempt->setTotalQuestions($questionCount);

                if (count($userPool) > 0) {
                    $attempt->setUser($userPool[array_rand($userPool)]);
                }

                $completed = random_int(1, 100) <= 70; // 70% completed
                $answers = [];

                if ($completed) {
                    $answeredCount = $questionCount;
                    $score = random_int((int) floor($maxScore * 0.2), $maxScore);
                    $attempt->setStatus('completed');
                    $attempt->setCurrentQuestionIndex(max(0, $questionCount - 1));
                    $attempt->setAnsweredCount($answeredCount);
                    $attempt->setScore($score);

                    foreach ($questionIds as $qid) {
                        $answers[$qid] = random_int(0, 4);
                    }

                    $completedAt = (clone $now)->modify('-' . random_int(0, 29) . ' days')->modify('-' . random_int(0, 23) . ' hours');
                    $attempt->setCompletedAt($completedAt);
                    $attempt->setUpdatedAt(clone $completedAt);
                } else {
                    $dropAt = random_int(0, max(0, $questionCount - 1));
                    $answeredCount = random_int(0, $dropAt + 1);
                    $attempt->setStatus('in_progress');
                    $attempt->setCurrentQuestionIndex($dropAt);
                    $attempt->setAnsweredCount($answeredCount);
                    $attempt->setScore(null);

                    $usedQuestionIds = array_slice($questionIds, 0, $answeredCount);
                    foreach ($usedQuestionIds as $qid) {
                        $answers[$qid] = random_int(0, 4);
                    }

                    $updatedAt = (clone $now)->modify('-' . random_int(1, 5) . ' hours');
                    $attempt->setUpdatedAt($updatedAt);
                }

                $attempt->setAnswersJson(json_encode($answers, JSON_UNESCAPED_UNICODE));
                $attempt->setDetectedCategoriesJson(json_encode([], JSON_UNESCAPED_UNICODE));

                $this->em->persist($attempt);
                $created++;
            }
        }

        $this->em->flush();

        $io->success(sprintf('Created %d quiz_attempt rows.', $created));
        return Command::SUCCESS;
    }
}
