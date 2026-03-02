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
use App\Service\QuizAiGeneratedQuizBuilderService;
use App\Service\QuizAiRecommendationService;
use App\Service\QuizQuestionReformulationService;
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
    private const INVALID_PAYLOAD_MESSAGE = 'Payload invalide';
    private const INVALID_CSRF_MESSAGE = 'Token CSRF invalide';

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
        AiQuizGeneratorService $aiQuizGenerator,
        QuizAiGeneratedQuizBuilderService $generatedQuizBuilder
    ): Response {
        $form = $this->createForm(QuizAiGenerateType::class);
        $form->handleRequest($request);
        $response = $this->render('admin/quiz_ai_generate.html.twig', [
            'form' => $form->createView(),
        ]);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = (string) $form->get('category')->getData();
            $response = $this->redirectToRoute('app_quiz_generate_ai');

            try {
                $quiz = $generatedQuizBuilder->buildFromPayload(
                    $category,
                    $aiQuizGenerator->generateFromCategory($category)
                );

                if ($quiz instanceof Quiz) {
                    $em->persist($quiz);
                    $em->flush();
                    $this->addFlash('success', 'Quiz genere par IA avec succes.');
                    $response = $this->redirectToRoute('app_quiz_edit', ['id' => $quiz->getId()]);
                } else {
                    $this->addFlash('danger', 'Aucune question valide n a ete generee.');
                }
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Generation IA indisponible. Verifiez OPENAI_API_KEY puis reessayez.');
            }
        }

        return $response;
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
        $savedAnswers = [];
        $decodedAnswers = json_decode((string) $attempt->getAnswersJson(), true);
        if (is_array($decodedAnswers)) {
            foreach ($decodedAnswers as $key => $value) {
                $savedAnswers[(string) $key] = (int) $value;
            }
        }

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
        $errorResponse = null;

        if (!is_array($payload)) {
            $errorResponse = new JsonResponse(['ok' => false, 'message' => self::INVALID_PAYLOAD_MESSAGE], 400);
        } else {
            $csrf = (string) ($payload['_token'] ?? '');
            if (!$this->isCsrfTokenValid('quiz_progress_' . $quiz->getId(), $csrf)) {
                $errorResponse = new JsonResponse(['ok' => false, 'message' => self::INVALID_CSRF_MESSAGE], 403);
            }
        }

        if ($errorResponse instanceof JsonResponse) {
            return $errorResponse;
        }

        $attempt = $this->resolveAttemptFromPayload($quiz, $payload, $attemptRepository, $em);
        if (!$attempt instanceof QuizAttempt) {
            return new JsonResponse(['ok' => false, 'message' => 'Tentative introuvable'], 404);
        }

        $answers = $payload['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        $validQuestionIds = [];
        foreach ($quiz->getQuestions() as $question) {
            $questionId = $question->getId();
            if ($questionId !== null) {
                $validQuestionIds[(string) $questionId] = true;
            }
        }
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
        $validQuestionIds = [];
        foreach ($quiz->getQuestions() as $question) {
            $questionId = $question->getId();
            if ($questionId !== null) {
                $validQuestionIds[(string) $questionId] = true;
            }
        }
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
            $isProblematicAnswer = match ((string) $question->getType()) {
                'likert_0_4' => $answerValue >= 3,
                'likert_1_5' => $answerValue >= 4,
                'yes_no' => $answerValue === 1,
                default => false,
            };

            if (!$isProblematicAnswer) {
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
        $aiRecommendation = $this->loadQuizAiRecoForAttempt((int) $attempt->getId());

        $maxScore = max(1, $attempt->getTotalQuestions() * 5);
        $percentage = (int) round(((int) $attempt->getScore() / $maxScore) * 100);

        $pdf = $quizPdfReportService->buildResultPdf(
            $quiz,
            $attempt->getUser(),
            (int) $attempt->getScore(),
            $maxScore,
            $percentage,
            $recommandationItems,
            $aiRecommendation
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

        $sortParts = match ($sort) {
            'name_asc' => ['q.name', 'ASC', null, null],
            'name_desc' => ['q.name', 'DESC', null, null],
            'questions_asc' => ['questions_count', 'ASC', 'q.createdAt', 'DESC'],
            'questions_desc' => ['questions_count', 'DESC', 'q.createdAt', 'DESC'],
            'oldest' => ['q.createdAt', 'ASC', null, null],
            default => ['q.createdAt', 'DESC', null, null],
        };

        $qb->orderBy($sortParts[0], $sortParts[1]);
        if (is_string($sortParts[2]) && is_string($sortParts[3])) {
            $qb->addOrderBy($sortParts[2], $sortParts[3]);
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

    #[Route('/quiz/{id}/question/{questionId}/reformulate', name: 'app_quiz_question_reformulate', methods: ['POST'])]
    public function reformulateQuestion(
        Request $request,
        Quiz $quiz,
        int $questionId,
        QuizQuestionReformulationService $reformulationService
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $errorResponse = null;

        if (!is_array($payload)) {
            $errorResponse = new JsonResponse(['ok' => false, 'message' => self::INVALID_PAYLOAD_MESSAGE], 400);
        } else {
            $csrf = (string) ($payload['_token'] ?? '');
            if (!$this->isCsrfTokenValid('quiz_reformulate_' . $quiz->getId(), $csrf)) {
                $errorResponse = new JsonResponse(['ok' => false, 'message' => self::INVALID_CSRF_MESSAGE], 403);
            }
        }

        if ($errorResponse instanceof JsonResponse) {
            return $errorResponse;
        }

        $targetQuestion = null;
        foreach ($quiz->getQuestions() as $question) {
            if ($question->getId() === $questionId) {
                $targetQuestion = $question;
                break;
            }
        }

        $questionText = '';
        if (!$targetQuestion instanceof Question) {
            $errorResponse = new JsonResponse(['ok' => false, 'message' => 'Question introuvable pour ce quiz'], 404);
        } else {
            $questionText = trim((string) $targetQuestion->getText());
            if ($questionText === '') {
                $errorResponse = new JsonResponse(['ok' => false, 'message' => 'Texte de question vide'], 422);
            }
        }

        if ($errorResponse instanceof JsonResponse) {
            return $errorResponse;
        }

        $reformulated = $reformulationService->reformulateForPatient($questionText, (string) $quiz->getName());

        return new JsonResponse([
            'ok' => true,
            'original' => $questionText,
            'reformulated' => $reformulated,
        ]);
    }

    #[Route('/quiz', name: 'app_quiz_index', methods: ['GET'])]
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

        $durationHaving = match ($duration) {
            'quick' => 'COUNT(question.id) < 6',
            'medium' => 'COUNT(question.id) BETWEEN 6 AND 10',
            'long' => 'COUNT(question.id) > 10',
            default => null,
        };
        if (is_string($durationHaving)) {
            $qb->having($durationHaving);
        }

        $sortParts = match ($sort) {
            'name_asc' => ['q.name', 'ASC', null, null],
            'name_desc' => ['q.name', 'DESC', null, null],
            'questions_asc' => ['questions_count', 'ASC', 'q.createdAt', 'DESC'],
            'questions_desc' => ['questions_count', 'DESC', 'q.createdAt', 'DESC'],
            default => ['q.createdAt', 'DESC', null, null],
        };
        $qb->orderBy($sortParts[0], $sortParts[1]);
        if (is_string($sortParts[2]) && is_string($sortParts[3])) {
            $qb->addOrderBy($sortParts[2], $sortParts[3]);
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

    #[Route('/quiz/{id}/ai-recommendation', name: 'app_quiz_ai_recommendation', methods: ['POST'])]
    public function aiRecommendation(
        Request $request,
        Quiz $quiz,
        QuizAiRecommendationService $aiRecommendationService,
        QuizAttemptRepository $attemptRepository
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        $response = null;

        if (!is_array($payload)) {
            $response = new JsonResponse(['ok' => false, 'message' => self::INVALID_PAYLOAD_MESSAGE], 400);
        } else {
            $csrf = (string) ($payload['_token'] ?? '');
            if (!$this->isCsrfTokenValid('quiz_ai_reco_' . $quiz->getId(), $csrf)) {
                $response = new JsonResponse(['ok' => false, 'message' => self::INVALID_CSRF_MESSAGE], 403);
            } else {
                $score = max(0, (int) ($payload['score'] ?? 0));
                $maxScore = max(1, (int) ($payload['max_score'] ?? 1));
                $percentage = (int) round(($score / $maxScore) * 100);
                $detectedProblems = array_values(array_unique($this->sanitizeScalarStringList($payload['detected_problems'] ?? [])));
                $existingRecommendations = $this->sanitizeScalarStringList($payload['existing_recommendations'] ?? []);
                $attemptId = max(0, (int) ($payload['attempt_id'] ?? 0));

                $attemptValidationError = $this->validateAiRecommendationAttempt($attemptId, $quiz, $attemptRepository);
                if ($attemptValidationError !== null) {
                    $response = $attemptValidationError;
                } else {
                    $result = $aiRecommendationService->generate(
                        (string) $quiz->getName(),
                        $score,
                        $maxScore,
                        $percentage,
                        $detectedProblems,
                        $existingRecommendations
                    );

                    if ($attemptId > 0) {
                        $this->saveQuizAiRecoForAttempt($attemptId, $result);
                    }

                    $response = new JsonResponse([
                        'ok' => true,
                        'summary' => $result['summary'],
                        'recommendations' => $result['recommendations'],
                        'videos' => $result['videos'],
                        'disclaimer' => $result['disclaimer'],
                    ]);
                }
            }
        }

        return $response;
    }

    /**
     * @param array<string,mixed> $aiPayload
     */
    private function saveQuizAiRecoForAttempt(int $attemptId, array $aiPayload): void
    {
        if ($attemptId <= 0) {
            return;
        }

        $dir = $this->getParameter('kernel.project_dir') . '/var/quiz_ai_reco';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $path = $dir . '/attempt_' . $attemptId . '.json';
        $payload = [
            'summary' => (string) ($aiPayload['summary'] ?? ''),
            'recommendations' => is_array($aiPayload['recommendations'] ?? null) ? $aiPayload['recommendations'] : [],
            'videos' => is_array($aiPayload['videos'] ?? null) ? $aiPayload['videos'] : [],
            'disclaimer' => (string) ($aiPayload['disclaimer'] ?? ''),
            'executed_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadQuizAiRecoForAttempt(int $attemptId): ?array
    {
        $decoded = null;

        if ($attemptId > 0) {
            $path = $this->getParameter('kernel.project_dir') . '/var/quiz_ai_reco/attempt_' . $attemptId . '.json';
            if (is_file($path)) {
                $raw = @file_get_contents($path);
                if (is_string($raw) && trim($raw) !== '') {
                    $data = json_decode($raw, true);
                    if (is_array($data)) {
                        $decoded = $data;
                    }
                }
            }
        }

        return $decoded;
    }

    /**
     * @return array<int, array{reco: Recommandation, selectedVideo: ?string}>
     */
    private function buildRecommendationItems(Quiz $quiz, int $totalScore, array $detectedProblems, RecommandationService $recommandationService): array
    {
        $sortBySeverity = static function (array $recommendations): array {
            usort($recommendations, static function (Recommandation $a, Recommandation $b): int {
                $order = ['high' => 3, 'medium' => 2, 'low' => 1];
                return ($order[$b->getSeverity()] ?? 1) <=> ($order[$a->getSeverity()] ?? 1);
            });

            return $recommendations;
        };

        $recommandations = $recommandationService->getFiltered($quiz, $totalScore, $detectedProblems);

        if (empty($recommandations)) {
            $scoreOnly = $quiz->getRecommandations()->filter(
                fn (Recommandation $reco) => $totalScore >= $reco->getMinScore() && $totalScore <= $reco->getMaxScore()
            )->toArray();
            $recommandations = $sortBySeverity($scoreOnly);
        }

        if (empty($recommandations)) {
            $recommandations = $sortBySeverity($quiz->getRecommandations()->toArray());
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
        $attempt = $attemptId > 0 ? $attemptRepository->find($attemptId) : null;

        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $attempt instanceof QuizAttempt ? $attempt : null;
        }

        if (!$attempt instanceof QuizAttempt) {
            return $this->getOrCreateAttempt($quiz, $em, $attemptRepository);
        }

        $matchesQuiz = $attempt->getQuiz()?->getId() === $quiz->getId();
        $matchesUser = $attempt->getUser()?->getId() === $user->getId();

        return ($matchesQuiz && $matchesUser) ? $attempt : null;
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private function sanitizeScalarStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $raw
        ), static fn (string $text): bool => $text !== ''));
    }

    private function validateAiRecommendationAttempt(int $attemptId, Quiz $quiz, QuizAttemptRepository $attemptRepository): ?JsonResponse
    {
        $errorResponse = null;

        if ($attemptId <= 0) {
            return $errorResponse;
        }

        $attempt = $attemptRepository->find($attemptId);
        if (!$attempt instanceof QuizAttempt) {
            $errorResponse = new JsonResponse(['ok' => false, 'message' => 'Tentative introuvable'], 404);
        } elseif ($attempt->getQuiz()?->getId() !== $quiz->getId()) {
            $errorResponse = new JsonResponse(['ok' => false, 'message' => 'Tentative non associee a ce quiz'], 400);
        } else {
            $user = $this->getUser();
            if ($user instanceof Utilisateur && $attempt->getUser() && $attempt->getUser()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
                $errorResponse = new JsonResponse(['ok' => false, 'message' => 'Acces refuse'], 403);
            }
        }

        return $errorResponse;
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

}
