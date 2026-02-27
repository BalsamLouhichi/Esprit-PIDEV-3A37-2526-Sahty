<?php

namespace App\Controller;

use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\Recommandation;
use App\Entity\Utilisateur;
use App\Form\QuizAiGenerateType;
use App\Form\QuizType;
use App\Repository\QuizAttemptRepository;
use App\Repository\QuizRepository;
use App\Service\AiQuizGeneratorService;
use App\Service\QuizPdfReportService;
use App\Service\RecommandationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
    #[Route('/quiz', name: 'app_quiz_index', methods: ['GET'])]
    public function index(Request $request, QuizRepository $quizRepository): Response
    {
        return $this->frontQuizList($quizRepository, $request);
    }

    #[Route('/admin/quiz/new', name: 'app_quiz_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $quiz = new Quiz();
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($quiz);
            $em->flush();
            $this->addFlash('success', 'Quiz ajoute avec succes.');
            return $this->redirectToRoute('admin_quiz_index');
        }

        return $this->render('admin/quiz_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/quiz/generate-ai', name: 'app_quiz_generate_ai', methods: ['GET', 'POST'])]
    public function generateAiQuiz(
        Request $request,
        EntityManagerInterface $em,
        AiQuizGeneratorService $aiQuizGenerator
    ): Response {
        $form = $this->createForm(QuizAiGenerateType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = (string) $form->get('category')->getData();

            try {
                $generatedQuiz = $aiQuizGenerator->generateFromCategory($category);
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Generation IA indisponible. Verifiez OPENAI_API_KEY puis reessayez.');
                return $this->redirectToRoute('app_quiz_generate_ai');
            }

            $quiz = new Quiz();
            $quiz->setName((string) ($generatedQuiz['name'] ?? 'Quiz IA - ' . ucfirst($category)));
            $quiz->setDescription((string) ($generatedQuiz['description'] ?? ('Quiz genere automatiquement pour la categorie ' . $category . '.')));

            $questions = $generatedQuiz['questions'] ?? [];
            if (!is_array($questions) || count($questions) === 0) {
                $this->addFlash('danger', 'Aucune question valide n a ete generee.');
                return $this->redirectToRoute('app_quiz_generate_ai');
            }

            foreach (array_values($questions) as $index => $questionData) {
                if (!is_array($questionData) || empty($questionData['text'])) {
                    continue;
                }

                $question = new Question();
                $question->setText((string) $questionData['text']);
                $question->setType((string) ($questionData['type'] ?? 'likert_0_4'));
                $question->setCategory((string) ($questionData['category'] ?? $category));
                $question->setReverse((bool) ($questionData['reverse'] ?? false));
                $question->setOrderInQuiz($index + 1);

                $quiz->addQuestion($question);
            }

            if ($quiz->getQuestions()->count() === 0) {
                $this->addFlash('danger', 'La generation IA a retourne des donnees invalides.');
                return $this->redirectToRoute('app_quiz_generate_ai');
            }

            $em->persist($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz genere par IA avec succes.');
            return $this->redirectToRoute('app_quiz_edit', ['id' => $quiz->getId()]);
        }

        return $this->render('admin/quiz_ai_generate.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/quiz/edit/{id}', name: 'app_quiz_edit', methods: ['GET', 'POST'])]
    public function edit(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Quiz modifie avec succes.');
            return $this->redirectToRoute('admin_quiz_index');
        }

        return $this->render('admin/quiz_form.html.twig', [
            'form' => $form->createView(),
            'quiz' => $quiz,
        ]);
    }

    #[Route('/admin/quiz/delete/{id}', name: 'app_quiz_delete', methods: ['POST'])]
    public function delete(Quiz $quiz, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $quiz->getId(), $request->request->get('_token'))) {
            $em->remove($quiz);
            $em->flush();
            $this->addFlash('success', 'Quiz supprime avec succes.');
        }

        return $this->redirectToRoute('admin_quiz_index');
    }

    #[Route('/quiz/{id}', name: 'app_quiz_show', methods: ['GET'])]
    public function show(Quiz $quiz, EntityManagerInterface $em, QuizAttemptRepository $attemptRepository): Response
    {
        $attempt = $this->getOrCreateAttempt($quiz, $em, $attemptRepository);
        $savedAnswers = $this->decodeJsonMap($attempt->getAnswersJson());

        return $this->render('quiz/front/show.html.twig', [
            'quiz' => $quiz,
            'questions' => $quiz->getQuestions(),
            'attempt' => $attempt,
            'saved_answers' => $savedAnswers,
            'saved_index' => $attempt->getCurrentQuestionIndex(),
        ]);
    }

    #[Route('/quiz/{id}/progress/save', name: 'app_quiz_progress_save', methods: ['POST'])]
    public function saveProgress(
        Request $request,
        Quiz $quiz,
        EntityManagerInterface $em,
        QuizAttemptRepository $attemptRepository
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['ok' => false, 'message' => 'Payload invalide'], 400);
        }

        $csrf = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('quiz_progress_' . $quiz->getId(), $csrf)) {
            return new JsonResponse(['ok' => false, 'message' => 'Token CSRF invalide'], 403);
        }

        $attempt = $this->resolveAttemptFromPayload($quiz, $payload, $attemptRepository, $em);
        if (!$attempt) {
            return new JsonResponse(['ok' => false, 'message' => 'Tentative introuvable'], 404);
        }

        $answers = $payload['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        $validQuestionIds = $this->buildValidQuestionIdMap($quiz);
        $sanitizedAnswers = [];
        foreach ($answers as $questionId => $value) {
            $questionId = (string) $questionId;
            if (!isset($validQuestionIds[$questionId])) {
                continue;
            }
            $sanitizedAnswers[$questionId] = (int) $value;
        }

        $attempt->setAnswersJson(json_encode($sanitizedAnswers, JSON_UNESCAPED_UNICODE));
        $attempt->setCurrentQuestionIndex(min(max((int) ($payload['currentIndex'] ?? 0), 0), max(0, count($quiz->getQuestions()) - 1)));
        $attempt->setAnsweredCount(count($sanitizedAnswers));
        $attempt->setTotalQuestions(count($quiz->getQuestions()));
        $attempt->setUpdatedAt(new \DateTime());

        if ($attempt->getStatus() !== 'completed') {
            $attempt->setStatus('in_progress');
        }

        $em->persist($attempt);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'attemptId' => $attempt->getId(),
            'savedAt' => $attempt->getUpdatedAt()?->format(DATE_ATOM),
        ]);
    }

    #[Route('/quiz/{id}/submit', name: 'app_quiz_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        Quiz $quiz,
        RecommandationService $recommandationService,
        EntityManagerInterface $em,
        QuizAttemptRepository $attemptRepository
    ): Response {
        $answers = $request->request->all('answers') ?? [];
        $validQuestionIds = $this->buildValidQuestionIdMap($quiz);
        $sanitizedAnswers = [];
        foreach ($answers as $questionId => $value) {
            $questionId = (string) $questionId;
            if (!isset($validQuestionIds[$questionId])) {
                continue;
            }
            $sanitizedAnswers[$questionId] = (int) $value;
        }
        $answers = $sanitizedAnswers;

        $totalScore = array_sum($answers);
        $detectedProblems = [];
        foreach ($quiz->getQuestions() as $question) {
            $questionId = (string) $question->getId();
            if (!array_key_exists($questionId, $answers)) {
                continue;
            }

            $answerValue = (int) $answers[$questionId];
            if (!$this->isProblemAnswer((string) $question->getType(), $answerValue)) {
                continue;
            }

            $category = $question->getCategory();
            if ($category) {
                $detectedProblems[] = mb_strtolower(trim($category));
            }
        }
        $detectedProblems = array_values(array_unique($detectedProblems));

        $recommandationItems = $this->buildRecommendationItems($quiz, $totalScore, $detectedProblems, $recommandationService);

        $attempt = $this->resolveAttemptFromPayload($quiz, ['attemptId' => $request->request->getInt('attempt_id')], $attemptRepository, $em);
        if ($attempt instanceof QuizAttempt) {
            $attempt->setStatus('completed');
            $attempt->setScore($totalScore);
            $attempt->setAnswersJson(json_encode($answers, JSON_UNESCAPED_UNICODE));
            $attempt->setDetectedCategoriesJson(json_encode($detectedProblems, JSON_UNESCAPED_UNICODE));
            $attempt->setAnsweredCount(count($answers));
            $attempt->setTotalQuestions(count($quiz->getQuestions()));
            $attempt->setCurrentQuestionIndex(max(0, count($quiz->getQuestions()) - 1));
            $attempt->setUpdatedAt(new \DateTime());
            $attempt->setCompletedAt(new \DateTime());
            $em->persist($attempt);
            $em->flush();
        }

        return $this->render('quiz/front/result.html.twig', [
            'quiz' => $quiz,
            'score' => $totalScore,
            'recommandations' => $recommandationItems,
            'total_questions' => count($quiz->getQuestions()),
            'detected_problems' => $detectedProblems,
            'attempt_id' => $attempt?->getId(),
        ]);
    }

    #[Route('/quiz/attempt/{id}/report-pdf', name: 'app_quiz_result_pdf', methods: ['GET'])]
    public function exportResultPdf(
        QuizAttempt $attempt,
        RecommandationService $recommandationService,
        QuizPdfReportService $quizPdfReportService
    ): Response {
        $user = $this->getUser();
        if ($user instanceof Utilisateur && $attempt->getUser() && $attempt->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Acces refuse');
        }

        if ($attempt->getStatus() !== 'completed' || $attempt->getScore() === null) {
            $this->addFlash('danger', 'Ce rapport n est pas encore disponible.');
            return $this->redirectToRoute('app_quiz_show', ['id' => $attempt->getQuiz()?->getId()]);
        }

        $quiz = $attempt->getQuiz();
        if (!$quiz) {
            throw $this->createNotFoundException('Quiz introuvable');
        }

        $detected = $this->decodeJsonList($attempt->getDetectedCategoriesJson());
        $recommandationItems = $this->buildRecommendationItems($quiz, (int) $attempt->getScore(), $detected, $recommandationService);

        $maxScore = max(1, $attempt->getTotalQuestions() * 5);
        $percentage = (int) round(((int) $attempt->getScore() / $maxScore) * 100);

        $pdf = $quizPdfReportService->buildResultPdf(
            $quiz,
            $attempt->getUser(),
            (int) $attempt->getScore(),
            $maxScore,
            $percentage,
            $recommandationItems
        );

        $filename = sprintf('quiz-report-%d.pdf', $attempt->getId());
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/admin/quizzes', name: 'admin_quiz_index', methods: ['GET'])]
    public function adminIndex(Request $request, QuizRepository $quizRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        $minQuestions = max(0, $request->query->getInt('min_questions', 0));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $quizRepository->createQueryBuilder('q')
            ->leftJoin('q.questions', 'question')
            ->addSelect('COUNT(question.id) AS HIDDEN questions_count')
            ->groupBy('q.id');

        if ($search !== '') {
            $qb->andWhere('LOWER(q.name) LIKE :search OR LOWER(q.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($minQuestions > 0) {
            $qb->having('COUNT(question.id) >= :minQuestions')
                ->setParameter('minQuestions', $minQuestions);
        }

        switch ($sort) {
            case 'name_asc':
                $qb->orderBy('q.name', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('q.name', 'DESC');
                break;
            case 'questions_asc':
                $qb->orderBy('questions_count', 'ASC')->addOrderBy('q.createdAt', 'DESC');
                break;
            case 'questions_desc':
                $qb->orderBy('questions_count', 'DESC')->addOrderBy('q.createdAt', 'DESC');
                break;
            case 'oldest':
                $qb->orderBy('q.createdAt', 'ASC');
                break;
            default:
                $qb->orderBy('q.createdAt', 'DESC');
                break;
        }

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $totalQuizzes = count($paginator);
        $totalPages = max(1, (int) ceil($totalQuizzes / $limit));

        return $this->render('admin/quiz_list.html.twig', [
            'quizzes' => iterator_to_array($paginator),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_quizzes' => $totalQuizzes,
            'active_search' => $search,
            'active_sort' => $sort,
            'active_min_questions' => $minQuestions,
        ]);
    }

    #[Route('/quizzes', name: 'app_quiz_front_list', methods: ['GET'])]
    public function frontQuizList(QuizRepository $quizRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 9;
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        $duration = (string) $request->query->get('duration', '');
        $category = mb_strtolower(trim((string) $request->query->get('category', '')));

        $qb = $quizRepository->createQueryBuilder('q')
            ->leftJoin('q.questions', 'question')
            ->addSelect('COUNT(question.id) AS HIDDEN questions_count')
            ->groupBy('q.id');

        if ($search !== '') {
            $qb->andWhere('LOWER(q.name) LIKE :search OR LOWER(q.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($category !== '') {
            $qb->andWhere('LOWER(question.category) = :category')
                ->setParameter('category', $category);
        }

        if ($duration === 'quick') {
            $qb->having('COUNT(question.id) < 6');
        } elseif ($duration === 'medium') {
            $qb->having('COUNT(question.id) BETWEEN 6 AND 10');
        } elseif ($duration === 'long') {
            $qb->having('COUNT(question.id) > 10');
        }

        switch ($sort) {
            case 'name_asc':
                $qb->orderBy('q.name', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('q.name', 'DESC');
                break;
            case 'questions_asc':
                $qb->orderBy('questions_count', 'ASC')->addOrderBy('q.createdAt', 'DESC');
                break;
            case 'questions_desc':
                $qb->orderBy('questions_count', 'DESC')->addOrderBy('q.createdAt', 'DESC');
                break;
            default:
                $qb->orderBy('q.createdAt', 'DESC');
                break;
        }

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $quizzes = iterator_to_array($paginator);
        $totalQuizzes = count($paginator);
        $totalPages = max(1, (int) ceil($totalQuizzes / $limit));

        $categories = [
            ['id' => 'stress', 'name' => 'Stress'],
            ['id' => 'anxiete', 'name' => 'Anxiete'],
            ['id' => 'sommeil', 'name' => 'Sommeil'],
            ['id' => 'concentration', 'name' => 'Concentration'],
            ['id' => 'humeur', 'name' => 'Humeur'],
            ['id' => 'bien_etre', 'name' => 'Bien-etre'],
        ];

        return $this->render('quiz/front/list.html.twig', [
            'quizzes' => $quizzes,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_quizzes' => $totalQuizzes,
            'categories' => $categories,
            'active_search' => $search,
            'active_sort' => $sort,
            'active_duration' => $duration,
            'active_category' => $category,
        ]);
    }

    private function isProblemAnswer(string $type, int $answer): bool
    {
        return match ($type) {
            'likert_0_4' => $answer >= 3,
            'likert_1_5' => $answer >= 4,
            'yes_no' => $answer === 1,
            default => false,
        };
    }

    /**
     * @param Recommandation[] $recommendations
     * @return Recommandation[]
     */
    private function sortBySeverity(array $recommendations): array
    {
        usort($recommendations, function (Recommandation $a, Recommandation $b) {
            $order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($order[$b->getSeverity()] ?? 1) <=> ($order[$a->getSeverity()] ?? 1);
        });

        return $recommendations;
    }

    /**
     * @return array<int, array{reco: Recommandation, selectedVideo: ?string}>
     */
    private function buildRecommendationItems(Quiz $quiz, int $totalScore, array $detectedProblems, RecommandationService $recommandationService): array
    {
        $recommandations = $recommandationService->getFiltered($quiz, $totalScore, $detectedProblems);

        if (empty($recommandations)) {
            $scoreOnly = $quiz->getRecommandations()->filter(
                fn (Recommandation $reco) => $totalScore >= $reco->getMinScore() && $totalScore <= $reco->getMaxScore()
            )->toArray();
            $recommandations = $this->sortBySeverity($scoreOnly);
        }

        if (empty($recommandations)) {
            $recommandations = $this->sortBySeverity($quiz->getRecommandations()->toArray());
        }

        $items = [];
        foreach ($recommandations as $recommandation) {
            $items[] = [
                'reco' => $recommandation,
                'selectedVideo' => $recommandationService->resolveVideoUrl($recommandation),
            ];
        }

        return $items;
    }

    private function getOrCreateAttempt(Quiz $quiz, EntityManagerInterface $em, QuizAttemptRepository $attemptRepository): QuizAttempt
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            $attempt = new QuizAttempt();
            $attempt->setQuiz($quiz);
            $attempt->setTotalQuestions(count($quiz->getQuestions()));
            return $attempt;
        }

        $attempt = $attemptRepository->findLatestInProgressForUserAndQuiz($user, $quiz);
        if ($attempt instanceof QuizAttempt) {
            return $attempt;
        }

        $attempt = new QuizAttempt();
        $attempt->setQuiz($quiz);
        $attempt->setUser($user);
        $attempt->setStatus('in_progress');
        $attempt->setTotalQuestions(count($quiz->getQuestions()));
        $em->persist($attempt);
        $em->flush();

        return $attempt;
    }

    private function resolveAttemptFromPayload(
        Quiz $quiz,
        array $payload,
        QuizAttemptRepository $attemptRepository,
        EntityManagerInterface $em
    ): ?QuizAttempt {
        $attemptId = (int) ($payload['attemptId'] ?? 0);
        $attempt = null;
        if ($attemptId > 0) {
            $attempt = $attemptRepository->find($attemptId);
        }

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $attempt instanceof QuizAttempt ? $attempt : null;
        }

        if ($attempt instanceof QuizAttempt) {
            if ($attempt->getQuiz()?->getId() !== $quiz->getId()) {
                return null;
            }
            if ($attempt->getUser()?->getId() !== $user->getId()) {
                return null;
            }
            return $attempt;
        }

        return $this->getOrCreateAttempt($quiz, $em, $attemptRepository);
    }

    /**
     * @return array<string, int>
     */
    private function decodeJsonMap(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $key => $value) {
            $result[(string) $key] = (int) $value;
        }
        return $result;
    }

    /**
     * @return string[]
     */
    private function decodeJsonList(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $result = [];
        foreach ($decoded as $value) {
            $result[] = (string) $value;
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<string, bool>
     */
    private function buildValidQuestionIdMap(Quiz $quiz): array
    {
        $ids = [];
        foreach ($quiz->getQuestions() as $question) {
            $id = $question->getId();
            if ($id !== null) {
                $ids[(string) $id] = true;
            }
        }

        return $ids;
    }
}
