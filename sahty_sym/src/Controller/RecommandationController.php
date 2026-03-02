<?php

namespace App\Controller;

use App\Entity\Recommandation;
use App\Entity\Quiz;
use App\Form\RecommandationType;
use App\Repository\QuizRepository;
use App\Repository\RecommandationRepository;
use App\Service\RecommandationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/recommandation')]
class RecommandationController extends AbstractController
{
    #[Route('/recommandations', name: 'app_recommandation_front_list', methods: ['GET'])]
    public function frontRecommandationList(
        RecommandationRepository $recommandationRepository,
        RecommandationService $recommandationService,
        Request $request
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 9;

        $type = mb_strtolower(trim((string) $request->query->get('type', '')));
        $sort = (string) $request->query->get('sort', 'recent');
        $search = trim((string) $request->query->get('search', ''));

        $qb = $recommandationRepository->createQueryBuilder('r');

        if ($type !== '') {
            $qb->andWhere('LOWER(r.type_probleme) LIKE :type OR LOWER(r.target_categories) LIKE :type')
                ->setParameter('type', '%' . $type . '%');
        }

        if ($search !== '') {
            $qb->andWhere('LOWER(r.name) LIKE :search OR LOWER(r.title) LIKE :search OR LOWER(r.description) LIKE :search OR LOWER(r.type_probleme) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        switch ($sort) {
            case 'score_asc':
                $qb->orderBy('r.min_score', 'ASC');
                break;
            case 'score_desc':
                $qb->orderBy('r.max_score', 'DESC');
                break;
            case 'severity':
                $qb->orderBy('r.severity', 'DESC')
                    ->addOrderBy('r.createdAt', 'DESC');
                break;
            default:
                $qb->orderBy('r.createdAt', 'DESC');
                break;
        }

        $countQb = clone $qb;
        $totalRecommandations = (int) $countQb
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $recommandations = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = max(1, (int) ceil($totalRecommandations / $limit));

        $recoItems = [];
        foreach ($recommandations as $recommandation) {
            $recoItems[] = [
                'reco' => $recommandation,
                'selectedVideo' => $recommandationService->resolveVideoUrl($recommandation),
            ];
        }

        return $this->render('recommandation/front/list.html.twig', [
            'recommandations' => $recoItems,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_recommandations' => $totalRecommandations,
            'active_type' => $type,
            'active_sort' => $sort,
            'active_search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_recommandation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $recommandation = new Recommandation();
        $form = $this->createForm(RecommandationType::class, $recommandation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($recommandation);
            $em->flush();

            $this->addFlash('success', 'Recommandation creee avec succes.');
            return $this->redirectToRoute('admin_recommandation_index');
        }

        return $this->render('admin/recommandation_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/get-questions/{quizId}', name: 'app_recommandation_get_questions', methods: ['GET'])]
    public function getQuestions(int $quizId, QuizRepository $quizRepository): JsonResponse
    {
        $quiz = $quizRepository->find($quizId);
        if (!$quiz instanceof Quiz) {
            return new JsonResponse([]);
        }

        $choices = [];
        foreach ($quiz->getQuestions() as $question) {
            $choices[] = [
                'text' => (string) $question->getText(),
            ];
        }

        return new JsonResponse($choices);
    }

    #[Route('', name: 'admin_recommandation_index', methods: ['GET'])]
    #[Route('', name: 'app_recommandation_index', methods: ['GET'])]
    public function index(
        Request $request,
        RecommandationRepository $recommandationRepository,
        QuizRepository $quizRepository
    ): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $search = trim((string) $request->query->get('search', ''));
        $severity = trim((string) $request->query->get('severity', ''));
        $quizId = $request->query->getInt('quiz_id', 0);
        $type = trim((string) $request->query->get('type', ''));
        $sort = (string) $request->query->get('sort', 'recent');

        $qb = $recommandationRepository->createQueryBuilder('r')
            ->leftJoin('r.quiz', 'q')
            ->addSelect('q');

        if ($search !== '') {
            $qb->andWhere('LOWER(r.name) LIKE :search OR LOWER(r.title) LIKE :search OR LOWER(r.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($severity !== '') {
            $qb->andWhere('r.severity = :severity')
                ->setParameter('severity', $severity);
        }

        if ($quizId > 0) {
            $qb->andWhere('q.id = :quizId')
                ->setParameter('quizId', $quizId);
        }

        if ($type !== '') {
            $qb->andWhere('LOWER(r.type_probleme) LIKE :type OR LOWER(r.target_categories) LIKE :type')
                ->setParameter('type', '%' . mb_strtolower($type) . '%');
        }

        switch ($sort) {
            case 'score_asc':
                $qb->orderBy('r.min_score', 'ASC');
                break;
            case 'score_desc':
                $qb->orderBy('r.max_score', 'DESC');
                break;
            case 'severity':
                $qb->orderBy('r.severity', 'DESC')
                    ->addOrderBy('r.createdAt', 'DESC');
                break;
            case 'name_asc':
                $qb->orderBy('r.title', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('r.title', 'DESC');
                break;
            default:
                $qb->orderBy('r.createdAt', 'DESC');
                break;
        }

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $totalRecommandations = count($paginator);
        $totalPages = max(1, (int) ceil($totalRecommandations / $limit));

        $quizOptions = $quizRepository->createQueryBuilder('q')
            ->orderBy('q.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/recommandation_list.html.twig', [
            'recommandations' => iterator_to_array($paginator),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_recommandations' => $totalRecommandations,
            'active_search' => $search,
            'active_severity' => $severity,
            'active_quiz_id' => $quizId,
            'active_type' => $type,
            'active_sort' => $sort,
            'quiz_options' => $quizOptions,
        ]);
    }

    #[Route('/{id}', name: 'app_recommandation_show', methods: ['GET'])]
    public function show(Recommandation $recommandation): Response
    {
        return $this->render('recommandation/show.html.twig', [
            'recommandation' => $recommandation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_recommandation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recommandation $recommandation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RecommandationType::class, $recommandation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Recommandation modifiee avec succes.');
            return $this->redirectToRoute('admin_recommandation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/recommandation_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_recommandation_delete', methods: ['POST'])]
    public function delete(Request $request, Recommandation $recommandation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $recommandation->getId(), $request->request->getString('_token'))) {
            $em->remove($recommandation);
            $em->flush();
            $this->addFlash('success', 'Recommandation supprimee avec succes.');
        }

        return $this->redirectToRoute('admin_recommandation_index', [], Response::HTTP_SEE_OTHER);
    }
}
