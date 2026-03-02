<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Entity\FicheMedicale;
use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use App\Entity\Laboratoire;
use App\Entity\TypeAnalyse;
use App\Entity\DemandeAnalyse;
use App\Entity\LaboratoireTypeAnalyse;
use App\Entity\Quiz;
use App\Entity\Recommandation;
use App\Entity\QuizAttempt;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Repository\UtilisateurRepository;
use App\Repository\RendezVousRepository;
use App\Repository\FicheMedicaleRepository;
use App\Repository\PatientRepository;
use App\Repository\MedecinRepository;
use App\Repository\LaboratoireRepository;
use App\Repository\TypeAnalyseRepository;
use App\Repository\DemandeAnalyseRepository;
use App\Repository\ParapharmacieRepository;
use App\Repository\ProduitRepository;
use App\Repository\CommandeRepository;
use App\Repository\QuizRepository;
use App\Repository\RecommandationRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    private UtilisateurRepository $userRepo;
    private EntityManagerInterface $em;

    public function __construct(UtilisateurRepository $userRepo, EntityManagerInterface $em)
    {
        $this->userRepo = $userRepo;
        $this->em = $em;
    }

    // ==================== TABLEAU DE BORD PRINCIPAL ====================

    #[Route('/', name: 'index')]
    public function index(
        RendezVousRepository $rdvRepo,
        FicheMedicaleRepository $ficheRepo,
        MedecinRepository $medecinRepo,
        PatientRepository $patientRepo,
        LaboratoireRepository $laboratoireRepo,
        DemandeAnalyseRepository $demandeRepo,
        ParapharmacieRepository $parapharmacieRepo,
        ProduitRepository $produitRepo,
        CommandeRepository $commandeRepo,
        QuizRepository $quizRepo,
        RecommandationRepository $recoRepo,
        QuizAttemptRepository $attemptRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Statistiques de base
        $totalUsers = $this->userRepo->count([]);
        $totalMedecins = $this->userRepo->count(['role' => 'medecin']);
        $totalPatients = $this->userRepo->count(['role' => 'patient']);
        $totalResponsableLabo = $this->userRepo->count(['role' => 'responsable_labo']);
        $totalResponsablePara = $this->userRepo->count(['role' => 'responsable_para']);
        $totalLaboratoires = $laboratoireRepo->count([]);
        $totalDemandesAnalyse = $demandeRepo->count([]);
        $totalParapharmacies = $parapharmacieRepo->count([]);
        $totalProduits = $produitRepo->count([]);
        $totalCommandes = $commandeRepo->count([]);
        $totalInactive = $this->userRepo->count(['estActif' => false]);
        $totalActive = $totalUsers - $totalInactive;

        // Statistiques pour la sidebar
        $totalRendezVous = $rdvRepo->count([]);
        $totalEvenements = $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from('App\Entity\Evenement', 'e')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalFichesMedicales = $ficheRepo->count([]);

        // ========== STATISTIQUES QUIZ ==========
        $totalQuizzes = $quizRepo->count([]);
        $totalQuestions = (int) ($quizRepo->createQueryBuilder('q')
            ->select('SUM(SIZE(q.questions))')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
        
        // Participants aux quiz
        $quizParticipants = $attemptRepo->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.user)')
            ->where('a.user IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Taux de rÃ©ussite moyen
        /** @var list<QuizAttempt> $completedAttempts */
        $completedAttempts = $attemptRepo->findBy(['status' => 'completed']);
        $totalScore = 0;
        $totalMaxScore = 0;
        foreach ($completedAttempts as $attempt) {
            $totalScore += $attempt->getScore() ?? 0;
            $totalMaxScore += $attempt->getTotalQuestions() * 5;
        }
        $quizSuccessRate = $totalMaxScore > 0 ? round(($totalScore / $totalMaxScore) * 100) : 0;
        
        // Quiz rÃ©cents
        /** @var list<Quiz> $recentQuizzes */
        $recentQuizzes = $quizRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        // DerniÃ¨re mise Ã  jour quiz
        /** @var Quiz|null $lastQuiz */
        $lastQuiz = $quizRepo->findOneBy([], ['updatedAt' => 'DESC']);
        $lastQuizCreatedAt = $lastQuiz?->getCreatedAt();
        $lastQuizUpdate = $lastQuiz && $lastQuiz->getUpdatedAt() ?
            $lastQuiz->getUpdatedAt()->format('d/m/Y') :
            ($lastQuizCreatedAt ? $lastQuizCreatedAt->format('d/m/Y') : 'â€”');
        
        // Moyenne questions par quiz
        $quizAvgQuestions = $totalQuizzes > 0 ? round($totalQuestions / $totalQuizzes, 1) : 0;

        // ========== STATISTIQUES RECOMMANDATIONS ==========
        $totalRecommandations = $recoRepo->count([]);
        
        $recommandationsBySeverity = [
            'low' => $recoRepo->count(['severity' => 'low']),
            'medium' => $recoRepo->count(['severity' => 'medium']),
            'high' => $recoRepo->count(['severity' => 'high']),
        ];
        
        $recommandationsWithVideo = $recoRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.video_url IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
        
        // Recommandations rÃ©centes
        /** @var list<Recommandation> $recentRecommandations */
        $recentRecommandations = $recoRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        // DerniÃ¨re mise Ã  jour recommandation
        /** @var Recommandation|null $lastReco */
        $lastReco = $recoRepo->findOneBy([], ['createdAt' => 'DESC']);
        $lastRecoCreatedAt = $lastReco?->getCreatedAt();
        $lastRecoUpdate = $lastRecoCreatedAt ? $lastRecoCreatedAt->format('d/m/Y') : 'â€”';
        
        // Recommandations approuvÃ©es (low + medium)
        $recommandationsApprouvees = $recommandationsBySeverity['low'] + $recommandationsBySeverity['medium'];
        $recommandationsEnAttente = 0; // Ã€ implÃ©menter si systÃ¨me d'approbation

        // Statistiques des demandes d'analyse
        $demandesEnAttente = $demandeRepo->count(['statut' => 'en_attente']);
        $demandesEnCours = $demandeRepo->count(['statut' => 'en_cours']);
        $demandesTerminees = $demandeRepo->count(['statut' => 'termine']);
        $commandesEnAttente = $commandeRepo->count(['statut' => 'en_attente']);
        
        // Laboratoires rÃ©cents
        $recentLaboratoires = $laboratoireRepo->findBy([], ['cree_le' => 'DESC'], 5);
        
        // Demandes d'analyse rÃ©centes
        $recentDemandes = $demandeRepo->findBy([], ['date_demande' => 'DESC'], 5);

        // Pourcentages de distribution
        $doctorsPercent = $totalUsers > 0 ? round(($totalMedecins / $totalUsers) * 100) : 0;
        $patientsPercent = $totalUsers > 0 ? round(($totalPatients / $totalUsers) * 100) : 0;
        $staffPercent = $totalUsers > 0 ? round((($totalResponsableLabo + $totalResponsablePara) / $totalUsers) * 100) : 0;
        $adminPercent = max(0, 100 - ($doctorsPercent + $patientsPercent + $staffPercent));

        // Utilisateurs rÃ©cents
        $recentUsers = $this->userRepo->findBy([], ['creeLe' => 'DESC'], 5);

        // Rendez-vous rÃ©cents
        $recentAppointments = $rdvRepo->findBy([], ['dateRdv' => 'DESC', 'heureRdv' => 'DESC'], 10);

        // Fiches mÃ©dicales rÃ©centes
        $recentMedicalRecords = $ficheRepo->findBy([], ['creeLe' => 'DESC'], 10);

        // Comptages pour chaque utilisateur
        $appointmentsCount = [];
        $medicalRecordsCount = [];
        
        foreach ($recentUsers as $user) {
            if ($user instanceof Patient) {
                $appointmentsCount[$user->getId()] = $rdvRepo->count(['patient' => $user]);
                $medicalRecordsCount[$user->getId()] = $ficheRepo->count(['patient' => $user]);
            } elseif ($user instanceof Medecin) {
                $appointmentsCount[$user->getId()] = $rdvRepo->count(['medecin' => $user]);
            }
        }

        // DonnÃ©es pour les graphiques
        $months = ['Janvier', 'FÃ©vrier', 'Mars', 'Avril', 'Mai', 'Juin', 
                   'Juillet', 'AoÃ»t', 'Septembre', 'Octobre', 'Novembre', 'DÃ©cembre'];
        
        // DonnÃ©es rÃ©elles
        $appointmentsData = $this->getMonthlyAppointmentsData($rdvRepo);
        $topMedecins = $this->getTopMedecins($rdvRepo, $medecinRepo);
        $topPatients = $this->getTopPatients($rdvRepo, $patientRepo);
        
        // DonnÃ©es IMC
        $imcStats = $this->getImcStatistics($ficheRepo);
        
        // Statistiques des rendez-vous par statut
        $statusStats = [
            'ConfirmÃ©' => $rdvRepo->count(['statut' => 'ConfirmÃ©']),
            'En attente' => $rdvRepo->count(['statut' => 'En attente']),
            'AnnulÃ©' => $rdvRepo->count(['statut' => 'AnnulÃ©']),
            'TerminÃ©' => $rdvRepo->count(['statut' => 'TerminÃ©']),
        ];

        // Statistiques des fiches mÃ©dicales
        $ficheStatusStats = [
            'Actif' => $ficheRepo->count(['statut' => 'actif']),
            'Inactif' => $ficheRepo->count(['statut' => 'inactif']),
        ];

        return $this->render('admin/index.html.twig', [
            'totalUsers' => $totalUsers,
            'totalMedecins' => $totalMedecins,
            'totalPatients' => $totalPatients,
            'totalInactive' => $totalInactive,
            'totalActive' => $totalActive,
            'totalResponsableLabo' => $totalResponsableLabo,
            'totalResponsablePara' => $totalResponsablePara,
            'totalLaboratoires' => $totalLaboratoires,
            'totalDemandesAnalyse' => $totalDemandesAnalyse,
            'totalParapharmacies' => $totalParapharmacies,
            'totalProduits' => $totalProduits,
            'totalCommandes' => $totalCommandes,
            'totalRendezVous' => $totalRendezVous,
            'totalEvenements' => $totalEvenements,
            'totalFichesMedicales' => $totalFichesMedicales,
            
            // Variables Quiz
            'totalQuizzes' => $totalQuizzes,
            'totalQuestions' => $totalQuestions,
            'quiz_participants' => $quizParticipants,
            'quiz_success_rate' => $quizSuccessRate,
            'last_quiz_update' => $lastQuizUpdate,
            'recentQuizzes' => $recentQuizzes,
            'quiz_avg_questions' => $quizAvgQuestions,
            
            // Variables Recommandations
            'totalRecommandations' => $totalRecommandations,
            'recommandationsBySeverity' => $recommandationsBySeverity,
            'recommandations_with_video' => $recommandationsWithVideo,
            'last_recommandation_update' => $lastRecoUpdate,
            'recentRecommandations' => $recentRecommandations,
            'recommandations_approuvees' => $recommandationsApprouvees,
            'recommandations_en_attente' => $recommandationsEnAttente,
            
            'recent_laboratoires' => $recentLaboratoires,
            'recent_demandes_analyse' => $recentDemandes,
            'stats' => [
                'total_users' => $totalUsers,
                'active_doctors' => $totalMedecins,
                'todays_appointments' => $rdvRepo->count(['dateRdv' => new \DateTime()]),
                'pending_appointments' => $rdvRepo->count(['statut' => 'En attente']),
                'todays_patients' => $rdvRepo->count(['dateRdv' => new \DateTime()]),
                'available_doctors' => $totalMedecins,
                'weekly_appointments' => $this->getWeeklyAppointmentsData($rdvRepo),
                'demandes_en_attente' => $demandesEnAttente,
                'demandes_en_cours' => $demandesEnCours,
                'demandes_terminees' => $demandesTerminees,
                'analyses_en_cours' => $demandesEnCours,
                'analyses_terminees' => $demandesTerminees,
                'commandes_en_attente' => $commandesEnAttente,
                'demandes_evenements' => 0,
            ],
            'charts' => [
                'months' => $months,
                'appointments_data' => $appointmentsData,
                'medecin_names' => array_column($topMedecins, 'name'),
                'medecin_counts' => array_column($topMedecins, 'count'),
                'patient_names' => array_column($topPatients, 'name'),
                'patient_counts' => array_column($topPatients, 'count'),
                'imc_labels' => array_keys($imcStats),
                'imc_counts' => array_values($imcStats),
                'status_labels' => array_keys($statusStats),
                'status_counts' => array_values($statusStats),
                'fiche_status_labels' => array_keys($ficheStatusStats),
                'fiche_status_counts' => array_values($ficheStatusStats),
            ],
            'system_status' => [
                'server_load' => 65,
                'database_usage' => 42,
                'storage' => 78,
                'overall' => 'operational',
            ],
            'user_distribution' => [
                'doctors' => $doctorsPercent,
                'patients' => $patientsPercent,
                'staff' => $staffPercent,
                'admin' => $adminPercent,
            ],
            'recent_appointments' => $recentAppointments,
            'recent_medical_records' => $recentMedicalRecords,
            'recent_activities' => [],
            'recent_users' => $recentUsers,
            'appointments_count' => $appointmentsCount,
            'medical_records_count' => $medicalRecordsCount,
            'app_name' => 'Sahty',
            'app_version' => '1.0.0',
        ]);
    }

    // ==================== ROUTES POUR LES QUIZZES ====================

    #[Route('/quizzes', name: 'quiz_index')]
    public function quizIndex(Request $request, QuizRepository $quizRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // ParamÃ¨tres de filtrage et tri
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'recent');
        $minQuestions = $request->query->getInt('min_questions', 0);
        $page = $request->query->getInt('page', 1);
        $limit = 10;
        
        // Construction de la requÃªte
        $qb = $quizRepo->createQueryBuilder('q')
            ->leftJoin('q.questions', 'quest')
            ->groupBy('q.id');
        
        // Recherche
        if ($search) {
            $qb->andWhere('q.name LIKE :search OR q.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre minimum de questions
        if ($minQuestions > 0) {
            $qb->having('COUNT(quest.id) >= :minQuestions')
               ->setParameter('minQuestions', $minQuestions);
        }
        
        // Tri
        switch ($sort) {
            case 'oldest':
                $qb->orderBy('q.createdAt', 'ASC');
                break;
            case 'name_asc':
                $qb->orderBy('q.name', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('q.name', 'DESC');
                break;
            case 'questions_asc':
                $qb->orderBy('COUNT(quest.id)', 'ASC');
                break;
            case 'questions_desc':
                $qb->orderBy('COUNT(quest.id)', 'DESC');
                break;
            default: // recent
                $qb->orderBy('q.createdAt', 'DESC');
        }
        
        // Pagination
        $totalQuizzes = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalQuizzes / $limit);
        
        $quizzes = $qb->setFirstResult(($page - 1) * $limit)
                      ->setMaxResults($limit)
                      ->getQuery()
                      ->getResult();
        
        // Statistiques pour la sidebar
        $totalQuestions = (int) ($quizRepo->createQueryBuilder('q')
            ->select('SUM(SIZE(q.questions))')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
        
        $totalRecommandations = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\Recommandation', 'r')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->render('admin/quiz/index.html.twig', [
            'quizzes' => $quizzes,
            'total_quizzes' => $totalQuizzes,
            'total_questions' => $totalQuestions,
            'total_recommandations' => $totalRecommandations,
            'active_search' => $search,
            'active_sort' => $sort,
            'active_min_questions' => $minQuestions,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/quiz/stats', name: 'quiz_stats')]
    public function quizStats(QuizRepository $quizRepo, QuizAttemptRepository $attemptRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var list<Quiz> $quizzes */
        $quizzes = $quizRepo->findAll();
        $totalAttempts = $attemptRepo->count([]);
        
        $stats = [
            'total_quizzes' => count($quizzes),
            'total_questions' => 0,
            'total_attempts' => $totalAttempts,
            'completed_attempts' => $attemptRepo->count(['status' => 'completed']),
            'in_progress_attempts' => $attemptRepo->count(['status' => 'in_progress']),
            'avg_score' => 0,
            'avg_completion_rate' => 0,
            'quizzes_with_questions' => 0,
            'quizzes_without_questions' => 0,
            'quizzes_with_attempts' => 0,
            'quizzes_without_attempts' => 0,
            'most_popular_quiz' => null,
            'most_questions_quiz' => null,
            'recent_quizzes' => [],
            'questions_distribution' => [
                '0-5' => 0,
                '6-10' => 0,
                '11-15' => 0,
                '16+' => 0,
            ],
            'attempts_by_month' => [],
            'success_rate_by_quiz' => [],
            'category_distribution' => [],
        ];
        
        $maxQuestions = 0;
        $maxAttempts = 0;
        $totalScore = 0;
        $totalCompleted = 0;
        $categoryCounts = [];
        
        // RÃ©cupÃ©rer les tentatives par mois
        /** @var list<array{month:int|string, year:int|string, count:int|string}> $attemptsByMonth */
        $attemptsByMonth = $attemptRepo->createQueryBuilder('a')
            ->select('MONTH(a.completedAt) as month, YEAR(a.completedAt) as year, COUNT(a.id) as count')
            ->where('a.completedAt IS NOT NULL')
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();
        
        foreach ($attemptsByMonth as $item) {
            $timestamp = mktime(0, 0, 0, (int) $item['month'], 1);
            if ($timestamp === false) {
                continue;
            }
            $monthName = date('F', $timestamp);
            $stats['attempts_by_month'][] = [
                'month' => $monthName . ' ' . $item['year'],
                'count' => $item['count']
            ];
        }
        
        foreach ($quizzes as $quiz) {
            $nbQuestions = $quiz->getQuestions()->count();
            $nbAttempts = $attemptRepo->count(['quiz' => $quiz]);
            $completedAttempts = $attemptRepo->count(['quiz' => $quiz, 'status' => 'completed']);
            
            $stats['total_questions'] += $nbQuestions;
            
            if ($nbQuestions > 0) {
                $stats['quizzes_with_questions']++;
                if ($nbQuestions > $maxQuestions) {
                    $maxQuestions = $nbQuestions;
                    $stats['most_questions_quiz'] = $quiz;
                }
            } else {
                $stats['quizzes_without_questions']++;
            }
            
            if ($nbAttempts > 0) {
                $stats['quizzes_with_attempts']++;
                if ($nbAttempts > $maxAttempts) {
                    $maxAttempts = $nbAttempts;
                    $stats['most_popular_quiz'] = $quiz;
                }
                
                if ($completedAttempts > 0) {
                    /** @var list<QuizAttempt> $attempts */
                    $attempts = $attemptRepo->findBy(['quiz' => $quiz, 'status' => 'completed']);
                    $quizTotalScore = 0;
                    foreach ($attempts as $attempt) {
                        $quizTotalScore += $attempt->getScore() ?? 0;
                    }
                    $avgQuizScore = $quizTotalScore / $completedAttempts;
                    $maxPossibleScore = $nbQuestions * 5;
                    $successRate = $maxPossibleScore > 0 ? round(($avgQuizScore / $maxPossibleScore) * 100, 1) : 0;
                    
                    $stats['success_rate_by_quiz'][] = [
                        'name' => $quiz->getName(),
                        'rate' => $successRate,
                        'attempts' => $completedAttempts
                    ];
                }
            } else {
                $stats['quizzes_without_attempts']++;
            }
            
            if ($nbQuestions <= 5) {
                $stats['questions_distribution']['0-5']++;
            } elseif ($nbQuestions <= 10) {
                $stats['questions_distribution']['6-10']++;
            } elseif ($nbQuestions <= 15) {
                $stats['questions_distribution']['11-15']++;
            } else {
                $stats['questions_distribution']['16+']++;
            }
            
            foreach ($quiz->getQuestions() as $question) {
                $category = $question->getCategory() ?? 'non catÃ©gorisÃ©';
                if (!isset($categoryCounts[$category])) {
                    $categoryCounts[$category] = 0;
                }
                $categoryCounts[$category]++;
            }
        }
        
        $completedAttempts = $attemptRepo->findBy(['status' => 'completed']);
        foreach ($completedAttempts as $attempt) {
            $totalScore += $attempt->getScore() ?? 0;
            $totalCompleted++;
        }
        
        if ($totalCompleted > 0) {
            $stats['avg_score'] = round($totalScore / $totalCompleted, 1);
            
            $totalQuestions = 0;
            $totalAnswered = 0;
            foreach ($completedAttempts as $attempt) {
                $totalQuestions += $attempt->getTotalQuestions();
                $totalAnswered += $attempt->getAnsweredCount();
            }
            $stats['avg_completion_rate'] = $totalQuestions > 0 ? round(($totalAnswered / $totalQuestions) * 100, 1) : 0;
        }
        
        arsort($categoryCounts);
        $stats['category_distribution'] = array_slice($categoryCounts, 0, 10);
        
        /** @var list<array{name:string,rate:float|int,attempts:int}> $successRateByQuiz */
        $successRateByQuiz = $stats['success_rate_by_quiz'];
        usort($successRateByQuiz, fn($a, $b) => $b['rate'] <=> $a['rate']);
        $stats['success_rate_by_quiz'] = array_slice($successRateByQuiz, 0, 5);
        
        $stats['recent_quizzes'] = $quizRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        return $this->render('admin/quiz/stats.html.twig', [
            'stats' => $stats
        ]);
    }

    #[Route('/quiz/attempts', name: 'quiz_attempts')]
    public function quizAttempts(Request $request, QuizAttemptRepository $attemptRepo, QuizRepository $quizRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $status = $request->query->get('status');
        $quizId = $request->query->getInt('quiz_id', 0);
        $userId = $request->query->getInt('user_id', 0);
        $search = $request->query->get('search');
        
        $qb = $attemptRepo->createQueryBuilder('a')
            ->leftJoin('a.quiz', 'q')
            ->leftJoin('a.user', 'u')
            ->addSelect('q', 'u')
            ->orderBy('a.createdAt', 'DESC');
        
        if ($status) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }
        
        if ($quizId > 0) {
            $qb->andWhere('a.quiz = :quizId')->setParameter('quizId', $quizId);
        }
        
        if ($userId > 0) {
            $qb->andWhere('a.user = :userId')->setParameter('userId', $userId);
        }
        
        if ($search) {
            $qb->andWhere('u.prenom LIKE :search OR u.nom LIKE :search OR q.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        $total = count($qb->getQuery()->getResult());
        $totalPages = ceil($total / $limit);
        
        $attempts = $qb->setFirstResult(($page - 1) * $limit)
                       ->setMaxResults($limit)
                       ->getQuery()
                       ->getResult();
        
        $quizOptions = $quizRepo->findBy([], ['name' => 'ASC']);
        
        return $this->render('admin/quiz/attempts.html.twig', [
            'attempts' => $attempts,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_attempts' => $total,
            'active_status' => $status,
            'active_quiz_id' => $quizId,
            'active_user_id' => $userId,
            'active_search' => $search,
            'quiz_options' => $quizOptions,
        ]);
    }

    #[Route('/quiz/attempt/{id}', name: 'quiz_attempt_view')]
    public function quizAttemptView(int $id, QuizAttemptRepository $attemptRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $attempt = $attemptRepo->find($id);
        if (!$attempt) {
            throw $this->createNotFoundException('Tentative non trouvÃ©e');
        }
        
        $answers = json_decode($attempt->getAnswersJson() ?? '{}', true);
        $detectedProblems = json_decode($attempt->getDetectedCategoriesJson() ?? '[]', true);
        
        $answersDetail = [];
        $quiz = $attempt->getQuiz();
        if ($quiz) {
            foreach ($quiz->getQuestions() as $question) {
                $questionId = (string) $question->getId();
                $answersDetail[] = [
                    'question' => $question,
                    'answer' => $answers[$questionId] ?? null,
                    'is_problem' => isset($answers[$questionId]) && $this->isProblemAnswer($question->getType(), $answers[$questionId]),
                ];
            }
        }
        
        return $this->render('admin/quiz/attempt_view.html.twig', [
            'attempt' => $attempt,
            'answers_detail' => $answersDetail,
            'detected_problems' => $detectedProblems,
        ]);
    }

    // ==================== ROUTES POUR LES RECOMMANDATIONS ====================

    #[Route('/recommandations', name: 'recommandation_index')]
    public function recommandationIndex(Request $request, RecommandationRepository $recoRepo, QuizRepository $quizRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // ParamÃ¨tres de filtrage et tri
        $search = $request->query->get('search');
        $severity = $request->query->get('severity');
        $quizId = $request->query->getInt('quiz_id', 0);
        $type = $request->query->get('type');
        $sort = $request->query->get('sort', 'recent');
        $page = $request->query->getInt('page', 1);
        $limit = 10;
        
        // Construction de la requÃªte
        $qb = $recoRepo->createQueryBuilder('r')
            ->leftJoin('r.quiz', 'q')
            ->addSelect('q');
        
        // Recherche
        if ($search) {
            $qb->andWhere('r.name LIKE :search OR r.title LIKE :search OR r.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par sÃ©vÃ©ritÃ©
        if ($severity) {
            $qb->andWhere('r.severity = :severity')
               ->setParameter('severity', $severity);
        }
        
        // Filtre par quiz
        if ($quizId) {
            $qb->andWhere('r.quiz = :quizId')
               ->setParameter('quizId', $quizId);
        }
        
        // Filtre par type de problÃ¨me
        if ($type) {
            $qb->andWhere('r.type_probleme LIKE :type')
               ->setParameter('type', '%' . $type . '%');
        }
        
        // Tri
        switch ($sort) {
            case 'name_asc':
                $qb->orderBy('r.name', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('r.name', 'DESC');
                break;
            case 'score_asc':
                $qb->orderBy('r.min_score', 'ASC');
                break;
            case 'score_desc':
                $qb->orderBy('r.max_score', 'DESC');
                break;
            case 'severity':
                $qb->orderBy('r.severity', 'DESC');
                break;
            default: // recent
                $qb->orderBy('r.createdAt', 'DESC');
        }
        
        // Pagination
        $totalRecommandations = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalRecommandations / $limit);
        
        $recommandations = $qb->setFirstResult(($page - 1) * $limit)
                              ->setMaxResults($limit)
                              ->getQuery()
                              ->getResult();
        
        // RÃ©cupÃ©rer tous les quizzes pour le filtre
        $quizOptions = $quizRepo->findBy([], ['name' => 'ASC']);
        
        // Statistiques pour la sidebar
        $highSeverityCount = $recoRepo->count(['severity' => 'high']);
        $mediumSeverityCount = $recoRepo->count(['severity' => 'medium']);
        $lowSeverityCount = $recoRepo->count(['severity' => 'low']);
        
        $manualVideoCount = $recoRepo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.video_url IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->render('admin/recommandation/index.html.twig', [
            'recommandations' => $recommandations,
            'total_recommandations' => $totalRecommandations,
            'high_severity_count' => $highSeverityCount,
            'medium_severity_count' => $mediumSeverityCount,
            'low_severity_count' => $lowSeverityCount,
            'manual_video_count' => $manualVideoCount,
            'quiz_options' => $quizOptions,
            'active_search' => $search,
            'active_severity' => $severity,
            'active_quiz_id' => $quizId,
            'active_type' => $type,
            'active_sort' => $sort,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/recommandations/stats', name: 'recommandation_stats')]
    public function recommandationStats(RecommandationRepository $recoRepo, QuizRepository $quizRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var list<Recommandation> $recommandations */
        $recommandations = $recoRepo->findAll();
        /** @var list<Quiz> $quizzes */
        $quizzes = $quizRepo->findAll();
        
        $stats = [
            'total_recommandations' => count($recommandations),
            'total_quizzes' => count($quizzes),
            'by_severity' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
            ],
            'by_video' => [
                'with_video' => 0,
                'without_video' => 0,
            ],
            'by_quiz' => [],
            'top_quizzes' => [],
            'avg_per_quiz' => 0,
            'score_ranges' => [
                '0-25' => 0,
                '26-50' => 0,
                '51-75' => 0,
                '76-100' => 0,
            ],
            'recent_recommandations' => [],
            'most_common_types' => [],
            'type_distribution' => [],
            'severity_percentage' => [],
        ];
        
        $typeCounts = [];
        /** @var array<int, array{id:int, name:string, count:int, questions_count:int}> $quizCounts */
        $quizCounts = [];
        
        foreach ($recommandations as $reco) {
            $stats['by_severity'][$reco->getSeverity()]++;
            
            if ($reco->getVideoUrl()) {
                $stats['by_video']['with_video']++;
            } else {
                $stats['by_video']['without_video']++;
            }
            
            $quiz = $reco->getQuiz();
            if ($quiz) {
                $quizId = $quiz->getId();
                if (!isset($quizCounts[$quizId])) {
                    $quizCounts[$quizId] = [
                        'id' => $quizId,
                        'name' => $quiz->getName(),
                        'count' => 0,
                        'questions_count' => $quiz->getQuestions()->count()
                    ];
                }
                $quizCounts[$quizId]['count']++;
            }
            
            $type = $reco->getTypeProbleme();
            if ($type) {
                if (!isset($typeCounts[$type])) {
                    $typeCounts[$type] = 0;
                }
                $typeCounts[$type]++;
            }
            
            $minScore = $reco->getMinScore();
            $maxScore = $reco->getMaxScore();
            $avgScore = ($minScore + $maxScore) / 2;
            
            if ($avgScore <= 25) {
                $stats['score_ranges']['0-25']++;
            } elseif ($avgScore <= 50) {
                $stats['score_ranges']['26-50']++;
            } elseif ($avgScore <= 75) {
                $stats['score_ranges']['51-75']++;
            } else {
                $stats['score_ranges']['76-100']++;
            }
        }
        
        if ($stats['total_quizzes'] > 0) {
            $stats['avg_per_quiz'] = round($stats['total_recommandations'] / $stats['total_quizzes'], 1);
        }
        
        foreach ($stats['by_severity'] as $severity => $count) {
            $stats['severity_percentage'][$severity] = $stats['total_recommandations'] > 0 
                ? round(($count / $stats['total_recommandations']) * 100, 1) 
                : 0;
        }
        
        $quizCountsList = array_values($quizCounts);
        usort($quizCountsList, fn($a, $b) => $b['count'] <=> $a['count']);
        $stats['top_quizzes'] = array_slice($quizCountsList, 0, 5);
        
        arsort($typeCounts);
        $stats['most_common_types'] = array_slice($typeCounts, 0, 5);
        
        foreach ($typeCounts as $type => $count) {
            $stats['type_distribution'][] = [
                'type' => $type,
                'count' => $count
            ];
        }
        
        $stats['recent_recommandations'] = $recoRepo->findBy([], ['createdAt' => 'DESC'], 5);
        
        foreach ($quizzes as $quiz) {
            $stats['by_quiz'][] = [
                'id' => $quiz->getId(),
                'name' => $quiz->getName(),
                'count' => $quiz->getRecommandations()->count(),
                'questions_count' => $quiz->getQuestions()->count(),
                'ratio' => $quiz->getQuestions()->count() > 0 
                    ? round($quiz->getRecommandations()->count() / $quiz->getQuestions()->count(), 2)
                    : 0
            ];
        }
        
        return $this->render('admin/recommandation/stats.html.twig', [
            'stats' => $stats
        ]);
    }

    #[Route('/recommandations/export', name: 'recommandation_export')]
    public function exportRecommandations(RecommandationRepository $recoRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var list<Recommandation> $recommandations */
        $recommandations = $recoRepo->findAll();
        
        $csv = "ID,Titre,Nom,Quiz,SÃ©vÃ©ritÃ©,Score Min,Score Max,Type ProblÃ¨me,CatÃ©gories Cibles,URL VidÃ©o,Date CrÃ©ation\n";
        
        foreach ($recommandations as $reco) {
            $quizName = $reco->getQuiz() ? $reco->getQuiz()->getName() : 'N/A';
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%d,%d,%s,%s,%s,%s\n",
                $reco->getId(),
                $reco->getTitle() ?? '',
                $reco->getName() ?? '',
                $quizName,
                $reco->getSeverity(),
                $reco->getMinScore(),
                $reco->getMaxScore(),
                $reco->getTypeProbleme() ?? '',
                $reco->getTargetCategories() ?? '',
                $reco->getVideoUrl() ?? '',
                $reco->getCreatedAt() ? $reco->getCreatedAt()->format('Y-m-d') : ''
            );
        }
        
        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="recommandations_export_' . date('Y-m-d') . '.csv"');
        
        return $response;
    }

    #[Route('/recommandations/check-coverage', name: 'recommandation_check_coverage')]
    public function checkRecommandationCoverage(RecommandationRepository $recoRepo, QuizRepository $quizRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var list<Quiz> $quizzes */
        $quizzes = $quizRepo->findAll();
        $coverage = [];
        
        foreach ($quizzes as $quiz) {
            $recommandations = $quiz->getRecommandations();
            $questions = $quiz->getQuestions();
            
            $scoreRanges = [];
            foreach ($recommandations as $reco) {
                $scoreRanges[] = [
                    'min' => $reco->getMinScore(),
                    'max' => $reco->getMaxScore(),
                    'title' => $reco->getTitle(),
                    'severity' => $reco->getSeverity()
                ];
            }
            
            usort($scoreRanges, fn($a, $b) => $a['min'] <=> $b['min']);
            
            $maxPossibleScore = $questions->count() * 5;
            $gaps = [];
            $lastMax = -1;
            
            foreach ($scoreRanges as $range) {
                if ($range['min'] > $lastMax + 1) {
                    $gaps[] = [
                        'from' => $lastMax + 1,
                        'to' => $range['min'] - 1
                    ];
                }
                $lastMax = max($lastMax, $range['max']);
            }
            
            if ($lastMax < $maxPossibleScore) {
                $gaps[] = [
                    'from' => $lastMax + 1,
                    'to' => $maxPossibleScore
                ];
            }
            
            $coverage[] = [
                'quiz' => $quiz,
                'total_recommandations' => $recommandations->count(),
                'score_ranges' => $scoreRanges,
                'gaps' => $gaps,
                'max_score' => $maxPossibleScore,
                'coverage_percentage' => $maxPossibleScore > 0 
                    ? round((($maxPossibleScore - array_sum(array_map(fn($g) => $g['to'] - $g['from'] + 1, $gaps))) / $maxPossibleScore) * 100, 1)
                    : 0
            ];
        }
        
        return $this->render('admin/recommandation/coverage.html.twig', [
            'coverage' => $coverage
        ]);
    }

    // ==================== ROUTES POUR LES LABORATOIRES ====================

    #[Route('/laboratoires', name: 'laboratoires')]
    public function laboratoires(Request $request, LaboratoireRepository $laboratoireRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $search = $request->query->get('search');
        $ville = $request->query->get('ville');
        $disponible = $request->query->get('disponible');
        
        $criteria = [];
        if ($ville) {
            $criteria['ville'] = $ville;
        }
        if ($disponible !== null && $disponible !== '') {
            $criteria['disponible'] = (bool)$disponible;
        }
        
        if ($search) {
            $laboratoires = $laboratoireRepo->createQueryBuilder('l')
                ->where('l.nom LIKE :search')
                ->orWhere('l.ville LIKE :search')
                ->orWhere('l.adresse LIKE :search')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('l.nom', 'ASC')
                ->getQuery()
                ->getResult();
        } else {
            $laboratoires = $laboratoireRepo->findBy($criteria, ['nom' => 'ASC']);
        }
        
        $villes = $laboratoireRepo->createQueryBuilder('l')
            ->select('DISTINCT l.ville')
            ->where('l.ville IS NOT NULL')
            ->orderBy('l.ville', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/laboratoires/index.html.twig', [
            'laboratoires' => $laboratoires,
            'villes' => $villes,
            'searchQuery' => $search,
            'selectedVille' => $ville,
            'selectedDisponible' => $disponible,
        ]);
    }
    
    #[Route('/laboratoire/new', name: 'laboratoire_new')]
    public function laboratoireNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('laboratoire_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_laboratoire_new');
            }

            $data = $request->request;
            
            $laboratoire = new Laboratoire();
            $laboratoire->setNom($data->getString('nom'));
            $laboratoire->setVille($data->getString('ville'));
            $laboratoire->setAdresse($data->getString('adresse'));
            $laboratoire->setTelephone($data->getString('telephone'));
            $laboratoire->setEmail($this->toNullableString($data->getString('email')));
            $laboratoire->setDescription($this->toNullableString($data->getString('description')));
            $laboratoire->setNumeroAgrement($this->toNullableString($data->getString('numeroAgrement')));
            if (($latitude = $this->toNullableString($data->getString('latitude'))) !== null) {
                $laboratoire->setLatitude((float) $latitude);
            }
            if (($longitude = $this->toNullableString($data->getString('longitude'))) !== null) {
                $laboratoire->setLongitude((float) $longitude);
            }
            $laboratoire->setDisponible($request->request->has('disponible'));
            
            $responsableId = $data->get('responsable_id');
            if ($responsableId) {
                $responsable = $this->userRepo->find($responsableId);
                if ($responsable instanceof ResponsableLaboratoire) {
                    $laboratoire->setResponsable($responsable);
                }
            }

            $this->em->persist($laboratoire);
            $this->em->flush();

            $this->addFlash('success', 'Laboratoire crÃ©Ã© avec succÃ¨s.');
            return $this->redirectToRoute('admin_laboratoire_view', ['id' => $laboratoire->getId()]);
        }

        $responsables = $this->userRepo->findBy(['role' => 'responsable_labo', 'estActif' => true]);

        return $this->render('admin/laboratoires/form.html.twig', [
            'laboratoire' => null,
            'responsables' => $responsables,
        ]);
    }

    #[Route('/laboratoire/{id}', name: 'laboratoire_view', requirements: ['id' => '\d+'])]
    public function laboratoireView(int $id, LaboratoireRepository $laboratoireRepo, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $laboratoire = $laboratoireRepo->find($id);
        if (!$laboratoire instanceof Laboratoire) {
            throw $this->createNotFoundException('Laboratoire non trouvÃ©');
        }

        $demandes = $demandeRepo->findBy(['laboratoire' => $laboratoire], ['date_demande' => 'DESC'], 10);

        return $this->render('admin/laboratoires/view.html.twig', [
            'laboratoire' => $laboratoire,
            'demandes' => $demandes,
        ]);
    }

    #[Route('/laboratoires/{id}/edit', name: 'laboratoire_edit', requirements: ['id' => '\d+'])]
    public function laboratoireEdit(Request $request, int $id, LaboratoireRepository $laboratoireRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $laboratoire = $laboratoireRepo->find($id);
        if (!$laboratoire instanceof Laboratoire) {
            throw $this->createNotFoundException('Laboratoire non trouvÃ©');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('laboratoire_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_laboratoire_edit', ['id' => $id]);
            }

            $data = $request->request;
            
            $laboratoire->setNom($data->getString('nom'));
            $laboratoire->setVille($data->getString('ville'));
            $laboratoire->setAdresse($data->getString('adresse'));
            $laboratoire->setTelephone($data->getString('telephone'));
            $laboratoire->setEmail($this->toNullableString($data->getString('email')));
            $laboratoire->setDescription($this->toNullableString($data->getString('description')));
            $laboratoire->setNumeroAgrement($this->toNullableString($data->getString('numeroAgrement')));
            if (($latitude = $this->toNullableString($data->getString('latitude'))) !== null) {
                $laboratoire->setLatitude((float) $latitude);
            }
            if (($longitude = $this->toNullableString($data->getString('longitude'))) !== null) {
                $laboratoire->setLongitude((float) $longitude);
            }
            $laboratoire->setDisponible($request->request->has('disponible'));
            
            $responsableId = $data->get('responsable_id');
            if ($responsableId) {
                $responsable = $this->userRepo->find($responsableId);
                if ($responsable instanceof ResponsableLaboratoire) {
                    $laboratoire->setResponsable($responsable);
                }
            } else {
                $laboratoire->setResponsable(null);
            }

            $this->em->flush();

            $this->addFlash('success', 'Laboratoire mis Ã  jour avec succÃ¨s.');
            return $this->redirectToRoute('admin_laboratoire_view', ['id' => $id]);
        }

        $responsables = $this->userRepo->findBy(['role' => 'responsable_labo', 'estActif' => true]);

        return $this->render('admin/laboratoires/form.html.twig', [
            'laboratoire' => $laboratoire,
            'responsables' => $responsables,
        ]);
    }

    #[Route('/laboratoires/{id}/delete', name: 'laboratoire_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function laboratoireDelete(Request $request, int $id, LaboratoireRepository $laboratoireRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $laboratoire = $laboratoireRepo->find($id);
        if (!$laboratoire instanceof Laboratoire) {
            $this->addFlash('danger', 'Laboratoire non trouvÃ©.');
            return $this->redirectToRoute('admin_laboratoires');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('delete-laboratoire' . $laboratoire->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_laboratoire_view', ['id' => $id]);
        }

        if ($laboratoire->getDemandeAnalyses()->count() > 0) {
            $this->addFlash('danger', 'Impossible de supprimer ce laboratoire car il a des demandes d\'analyse associÃ©es.');
            return $this->redirectToRoute('admin_laboratoire_view', ['id' => $id]);
        }

        $this->em->remove($laboratoire);
        $this->em->flush();

        $this->addFlash('success', 'Laboratoire supprimÃ© avec succÃ¨s.');
        return $this->redirectToRoute('admin_laboratoires');
    }

    #[Route('/laboratoires/{id}/toggle-disponibilite', name: 'laboratoire_toggle_disponibilite', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function laboratoireToggleDisponibilite(Request $request, int $id, LaboratoireRepository $laboratoireRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $laboratoire = $laboratoireRepo->find($id);
        if (!$laboratoire instanceof Laboratoire) {
            $this->addFlash('danger', 'Laboratoire non trouvÃ©.');
            return $this->redirectToRoute('admin_laboratoires');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('toggle-laboratoire' . $laboratoire->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_laboratoire_view', ['id' => $id]);
        }

        $laboratoire->setDisponible(!$laboratoire->isDisponible());
        $this->em->flush();

        $status = $laboratoire->isDisponible() ? 'disponible' : 'indisponible';
        $this->addFlash('success', "Laboratoire marquÃ© comme $status.");
        return $this->redirectToRoute('admin_laboratoire_view', ['id' => $id]);
    }

   #[Route('/laboratoires/stats', name: 'laboratoires_stats')]
    public function laboratoiresStats(LaboratoireRepository $laboratoireRepo, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        /** @var list<Laboratoire> $laboratoires */
        $laboratoires = $laboratoireRepo->findAll();
        
        $stats = [
            'total' => count($laboratoires),
            'disponibles' => 0,
            'indisponibles' => 0,
            'avec_responsable' => 0,
            'sans_responsable' => 0,
            'total_demandes' => 0,
            'demandes_par_labo' => [],
            'top_laboratoires' => [],
        ];

        foreach ($laboratoires as $labo) {
            if ($labo->isDisponible()) {
                $stats['disponibles']++;
            } else {
                $stats['indisponibles']++;
            }

            if ($labo->getResponsable()) {
                $stats['avec_responsable']++;
            } else {
                $stats['sans_responsable']++;
            }

            $nbDemandes = $labo->getDemandeAnalyses()->count();
            $stats['total_demandes'] += $nbDemandes;
            
            $stats['demandes_par_labo'][] = [
                'nom' => $labo->getNom(),
                'count' => $nbDemandes
            ];

            if ($nbDemandes > 0) {
                $responsable = $labo->getResponsable();
                $stats['top_laboratoires'][] = [
                    'id' => $labo->getId(),
                    'nom' => $labo->getNom(),
                    'ville' => $labo->getVille(),
                    'count' => $nbDemandes,
                    'responsable' => $responsable ? [
                        'prenom' => $responsable->getPrenom(),
                        'nom' => $responsable->getNom()
                    ] : null
                ];
            }
        }

        usort($stats['top_laboratoires'], fn($a, $b) => $b['count'] <=> $a['count']);
        $stats['top_laboratoires'] = array_slice($stats['top_laboratoires'], 0, 5);

        return $this->render('admin/laboratoires/stats.html.twig', [
            'stats' => $stats
        ]);
    }

    // ==================== ROUTES POUR LES TYPES D'ANALYSE ====================

    #[Route('/types-analyse', name: 'type_analyse_list')]
    public function typeAnalyseList(TypeAnalyseRepository $typeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $types = $typeRepo->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/type_analyse/index.html.twig', [
            'types' => $types,
        ]);
    }

    #[Route('/type-analyse/new', name: 'type_analyse_new')]
    public function typeAnalyseNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('type_analyse_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_type_analyse_new');
            }

            $data = $request->request;
            
            $type = new TypeAnalyse();
            $type->setNom($data->getString('nom'));
            $type->setDescription($data->getString('description'));
            $type->setActif($request->request->has('actif'));
            $type->setCategorie($this->toNullableString($data->getString('categorie')));

            $this->em->persist($type);
            $this->em->flush();

            $this->addFlash('success', 'Type d\'analyse crÃ©Ã© avec succÃ¨s.');
            return $this->redirectToRoute('admin_type_analyse_list');
        }

        return $this->render('admin/type_analyse/form.html.twig', [
            'type' => null,
        ]);
    }

    #[Route('/type-analyse/{id}/edit', name: 'type_analyse_edit', requirements: ['id' => '\d+'])]
    public function typeAnalyseEdit(Request $request, int $id, TypeAnalyseRepository $typeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $type = $typeRepo->find($id);
        if (!$type instanceof TypeAnalyse) {
            throw $this->createNotFoundException('Type d\'analyse non trouvÃ©');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('type_analyse_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_type_analyse_edit', ['id' => $id]);
            }

            $data = $request->request;
            
            $type->setNom($data->getString('nom'));
            $type->setDescription($data->getString('description'));
            $type->setActif($request->request->has('actif'));
            $type->setCategorie($this->toNullableString($data->getString('categorie')));

            $this->em->flush();

            $this->addFlash('success', 'Type d\'analyse mis Ã  jour avec succÃ¨s.');
            return $this->redirectToRoute('admin_type_analyse_list');
        }

        return $this->render('admin/type_analyse/form.html.twig', [
            'type' => $type,
        ]);
    }

    #[Route('/type-analyse/{id}/delete', name: 'type_analyse_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function typeAnalyseDelete(Request $request, int $id, TypeAnalyseRepository $typeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $type = $typeRepo->find($id);
        if (!$type instanceof TypeAnalyse) {
            $this->addFlash('danger', 'Type d\'analyse non trouvÃ©.');
            return $this->redirectToRoute('admin_type_analyse_list');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('delete-type-analyse' . $type->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_type_analyse_list');
        }

        if ($type->getLaboratoireTypeAnalyses()->count() > 0) {
            $this->addFlash('danger', 'Ce type d\'analyse est utilisÃ© par des laboratoires et ne peut pas Ãªtre supprimÃ©.');
            return $this->redirectToRoute('admin_type_analyse_list');
        }

        $this->em->remove($type);
        $this->em->flush();

        $this->addFlash('success', 'Type d\'analyse supprimÃ© avec succÃ¨s.');
        return $this->redirectToRoute('admin_type_analyse_list');
    }
    
    #[Route('/types-analyse/stats', name: 'type_analyse_stats')]
    public function typeAnalyseStats(TypeAnalyseRepository $typeRepo, LaboratoireRepository $laboratoireRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $types = $typeRepo->findAll();
        
        $stats = [
            'total' => count($types),
            'actifs' => 0,
            'inactifs' => 0,
            'total_laboratoires' => $laboratoireRepo->count([]),
            'types_par_laboratoire' => [],
            'top_types' => [],
            'repartition_actifs' => [],
            'par_categorie' => [],
            'nb_categories' => 0,
            'sans_laboratoire' => 0,
            'avec_laboratoire' => 0,
            'moyenne_par_type' => 0,
            'max_associations' => 0,
        ];
        
        $typesCount = [];
        $categoriesCount = [];
        $totalAssociations = 0;
        
        foreach ($types as $type) {
            if ($type->isActif()) {
                $stats['actifs']++;
            } else {
                $stats['inactifs']++;
            }
            
            $categorie = $type->getCategorie() ?: 'Sans catÃ©gorie';
            if (!isset($categoriesCount[$categorie])) {
                $categoriesCount[$categorie] = 0;
            }
            $categoriesCount[$categorie]++;
            
            $nbLabos = $type->getLaboratoireTypeAnalyses()->count();
            $totalAssociations += $nbLabos;
            
            if ($nbLabos > 0) {
                $stats['avec_laboratoire']++;
                $stats['max_associations'] = max($stats['max_associations'], $nbLabos);
                
                $typesCount[] = [
                    'nom' => $type->getNom(),
                    'categorie' => $type->getCategorie(),
                    'actif' => $type->isActif(),
                    'count' => $nbLabos
                ];
            } else {
                $stats['sans_laboratoire']++;
            }
        }
        
        $stats['moyenne_par_type'] = $stats['total'] > 0 ? round($totalAssociations / $stats['total'], 1) : 0;
        
        usort($typesCount, fn($a, $b) => $b['count'] <=> $a['count']);
        $stats['top_types'] = array_slice($typesCount, 0, 5);
        
        $stats['par_categorie'] = [];
        foreach ($categoriesCount as $categorie => $count) {
            $stats['par_categorie'][] = [
                'categorie' => $categorie,
                'count' => $count
            ];
        }
        $stats['nb_categories'] = count($categoriesCount);
        
        $stats['repartition_actifs'] = [
            ['statut' => 'Actifs', 'count' => $stats['actifs']],
            ['statut' => 'Inactifs', 'count' => $stats['inactifs']]
        ];
        
        return $this->render('admin/type_analyse/stats.html.twig', [
            'stats' => $stats
        ]);
    }

    // ==================== ROUTES POUR LES DEMANDES D'ANALYSE ====================

    #[Route('/demandes-analyse', name: 'demande_analyse_list')]
    public function demandeAnalyseList(Request $request, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $statut = $request->query->get('statut');
        $laboratoire = $request->query->get('laboratoire');
        
        $criteria = [];
        if ($statut) {
            $criteria['statut'] = $statut;
        }
        if ($laboratoire) {
            $criteria['laboratoire'] = $laboratoire;
        }
        
        $demandes = $demandeRepo->findBy($criteria, ['date_demande' => 'DESC']);

        return $this->render('admin/demande_analyse/index.html.twig', [
            'demandes' => $demandes,
            'selected_statut' => $statut,
            'selected_laboratoire' => $laboratoire,
        ]);
    }

    #[Route('/demande-analyse/new', name: 'demande_analyse_new')]
    public function demandeAnalyseNew(Request $request, LaboratoireRepository $laboratoireRepo, PatientRepository $patientRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('demande_analyse_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_demande_analyse_new');
            }

            $data = $request->request;
            
            $demande = new DemandeAnalyse();
            
            $patientId = $data->getInt('patient_id');
            if ($patientId > 0) {
                $patient = $patientRepo->find($patientId);
                if ($patient instanceof Patient) {
                    $demande->setPatient($patient);
                }
            }
            
            $laboratoireId = $data->getInt('laboratoire_id');
            if ($laboratoireId > 0) {
                $laboratoire = $laboratoireRepo->find($laboratoireId);
                if ($laboratoire instanceof Laboratoire) {
                    $demande->setLaboratoire($laboratoire);
                }
            }
            
            $demande->setTypeBilan($data->getString('type_bilan'));
            $demande->setStatut($data->getString('statut') !== '' ? $data->getString('statut') : 'en_attente');
            $demande->setPriorite($data->getString('priorite') !== '' ? $data->getString('priorite') : 'Normale');
            $demande->setNotes($this->toNullableString($data->getString('notes')));
            
            $analysesRaw = $request->request->all('analyses');
            /** @var list<string> $analyses */
            $analyses = array_values(array_map('strval', array_filter($analysesRaw, static fn (mixed $v): bool => is_scalar($v) && trim((string) $v) !== '')));
            $demande->setAnalyses($analyses);
            
            if (($programmeLe = $this->toNullableString($data->getString('programme_le'))) !== null) {
                try {
                    $demande->setProgrammeLe(new \DateTime($programmeLe));
                } catch (\Exception $e) {}
            }
            
            if (($envoyeLe = $this->toNullableString($data->getString('envoye_le'))) !== null) {
                try {
                    $demande->setEnvoyeLe(new \DateTime($envoyeLe));
                } catch (\Exception $e) {}
            }

            $pdfFile = $request->files->get('resultat_pdf');
            if ($pdfFile instanceof UploadedFile) {
                $uploadsDir = $this->getProjectDir() . '/public/uploads/resultats';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0777, true);
                }
                $filename = uniqid('resultat_') . '.' . $pdfFile->guessExtension();
                $pdfFile->move($uploadsDir, $filename);
                $demande->setResultatPdf($filename);
            }

            $this->em->persist($demande);
            $this->em->flush();

            $this->addFlash('success', 'Demande d\'analyse crÃ©Ã©e avec succÃ¨s.');
            return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $demande->getId()]);
        }

        $laboratoires = $laboratoireRepo->findBy(['disponible' => true], ['nom' => 'ASC']);
        $patients = $patientRepo->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);

        return $this->render('admin/demande_analyse/form.html.twig', [
            'demande' => null,
            'laboratoires' => $laboratoires,
            'patients' => $patients,
        ]);
    }

    #[Route('/demande-analyse/{id}', name: 'demande_analyse_view', requirements: ['id' => '\d+'])]
    public function demandeAnalyseView(int $id, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $demande = $demandeRepo->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande d\'analyse non trouvÃ©e');
        }

        return $this->render('admin/demande_analyse/view.html.twig', [
            'demande' => $demande,
        ]);
    }

    #[Route('/demande-analyse/{id}/edit', name: 'demande_analyse_edit', requirements: ['id' => '\d+'])]
    public function demandeAnalyseEdit(Request $request, int $id, DemandeAnalyseRepository $demandeRepo, LaboratoireRepository $laboratoireRepo, PatientRepository $patientRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $demande = $demandeRepo->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande d\'analyse non trouvÃ©e');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('demande_analyse_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_demande_analyse_edit', ['id' => $id]);
            }

            $data = $request->request;
            
            $patientId = $data->getInt('patient_id');
            if ($patientId > 0) {
                $patient = $patientRepo->find($patientId);
                if ($patient instanceof Patient) {
                    $demande->setPatient($patient);
                }
            }
            
            $laboratoireId = $data->getInt('laboratoire_id');
            if ($laboratoireId > 0) {
                $laboratoire = $laboratoireRepo->find($laboratoireId);
                if ($laboratoire instanceof Laboratoire) {
                    $demande->setLaboratoire($laboratoire);
                }
            }
            
            $demande->setTypeBilan($data->getString('type_bilan'));
            $demande->setStatut($data->getString('statut'));
            $demande->setPriorite($data->getString('priorite'));
            $demande->setNotes($this->toNullableString($data->getString('notes')));
            
            $analysesRaw = $request->request->all('analyses');
            /** @var list<string> $analyses */
            $analyses = array_values(array_map('strval', array_filter($analysesRaw, static fn (mixed $v): bool => is_scalar($v) && trim((string) $v) !== '')));
            $demande->setAnalyses($analyses);
            
            if (($programmeLe = $this->toNullableString($data->getString('programme_le'))) !== null) {
                try {
                    $demande->setProgrammeLe(new \DateTime($programmeLe));
                } catch (\Exception $e) {}
            } else {
                $demande->setProgrammeLe(null);
            }
            
            if (($envoyeLe = $this->toNullableString($data->getString('envoye_le'))) !== null) {
                try {
                    $demande->setEnvoyeLe(new \DateTime($envoyeLe));
                } catch (\Exception $e) {}
            } else {
                $demande->setEnvoyeLe(null);
            }

            $pdfFile = $request->files->get('resultat_pdf');
            if ($pdfFile instanceof UploadedFile) {
                $uploadsDir = $this->getProjectDir() . '/public/uploads/resultats';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0777, true);
                }
                $filename = uniqid('resultat_') . '.' . $pdfFile->guessExtension();
                $pdfFile->move($uploadsDir, $filename);
                $demande->setResultatPdf($filename);
            }

            $this->em->flush();

            $this->addFlash('success', 'Demande d\'analyse mise Ã  jour avec succÃ¨s.');
            return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
        }

        $laboratoires = $laboratoireRepo->findBy([], ['nom' => 'ASC']);
        $patients = $patientRepo->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);

        return $this->render('admin/demande_analyse/form.html.twig', [
            'demande' => $demande,
            'laboratoires' => $laboratoires,
            'patients' => $patients,
        ]);
    }

    #[Route('/demande-analyse/{id}/delete', name: 'demande_analyse_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function demandeAnalyseDelete(Request $request, int $id, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $demande = $demandeRepo->find($id);
        if (!$demande) {
            $this->addFlash('danger', 'Demande d\'analyse non trouvÃ©e.');
            return $this->redirectToRoute('admin_demande_analyse_list');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('delete-demande-analyse' . $demande->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
        }

        $this->em->remove($demande);
        $this->em->flush();

        $this->addFlash('success', 'Demande d\'analyse supprimÃ©e avec succÃ¨s.');
        return $this->redirectToRoute('admin_demande_analyse_list');
    }

    #[Route('/demande-analyse/{id}/update-statut', name: 'demande_analyse_update_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function demandeAnalyseUpdateStatut(Request $request, int $id, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $demande = $demandeRepo->find($id);
        if (!$demande) {
            $this->addFlash('danger', 'Demande d\'analyse non trouvÃ©e.');
            return $this->redirectToRoute('admin_demande_analyse_list');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('update-demande-statut' . $demande->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
        }

        $newStatut = $request->request->getString('statut');
        if ($newStatut === '') {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
        }
        $demande->setStatut($newStatut);
        
        if ($newStatut === 'en_cours') {
            $demande->setProgrammeLe(new \DateTime());
        } elseif ($newStatut === 'termine') {
            $demande->setEnvoyeLe(new \DateTime());
        }

        $this->em->flush();

        $this->addFlash('success', 'Statut de la demande mis Ã  jour avec succÃ¨s.');
        return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
    }

    #[Route('/demande-analyse/{id}/upload-resultat', name: 'demande_analyse_upload_resultat', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function demandeAnalyseUploadResultat(Request $request, int $id, DemandeAnalyseRepository $demandeRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $demande = $demandeRepo->find($id);
        if (!$demande) {
            $this->addFlash('danger', 'Demande d\'analyse non trouvÃ©e.');
            return $this->redirectToRoute('admin_demande_analyse_list');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('upload-resultat' . $demande->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
        }

        $pdfFile = $request->files->get('resultat_pdf');
        if ($pdfFile instanceof UploadedFile) {
            $uploadsDir = $this->getProjectDir() . '/public/uploads/resultats';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0777, true);
            }
            $filename = uniqid('resultat_') . '.' . $pdfFile->guessExtension();
            $pdfFile->move($uploadsDir, $filename);
            
            $oldFile = $demande->getResultatPdf();
            if ($oldFile && file_exists($uploadsDir . '/' . $oldFile)) {
                unlink($uploadsDir . '/' . $oldFile);
            }
            
            $demande->setResultatPdf($filename);
            $demande->setStatut('termine');
            $demande->setEnvoyeLe(new \DateTime());
            
            $this->em->flush();
            
            $this->addFlash('success', 'RÃ©sultat uploadÃ© avec succÃ¨s.');
        } else {
            $this->addFlash('danger', 'Veuillez sÃ©lectionner un fichier PDF.');
        }

        return $this->redirectToRoute('admin_demande_analyse_view', ['id' => $id]);
    }

    #[Route('/demandes-analyse/stats', name: 'demande_analyse_stats')]
    public function demandeAnalyseStats(DemandeAnalyseRepository $demandeRepo, LaboratoireRepository $laboratoireRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $demandes = $demandeRepo->findAll();
        
        $stats = [
            'total' => count($demandes),
            'en_attente' => 0,
            'en_cours' => 0,
            'termine' => 0,
            'annule' => 0,
            'priorite_haute' => 0,
            'priorite_moyenne' => 0,
            'priorite_normale' => 0,
            'total_patients' => 0,
            'total_laboratoires' => $laboratoireRepo->count([]),
            'demandes_par_mois' => [],
            'demandes_par_laboratoire' => [],
            'top_patients' => [],
            'delai_moyen_traitement' => 0,
        ];
        
        $patientsCount = [];
        $laboratoiresCount = [];
        $totalDelai = 0;
        $nbTermines = 0;
        
        foreach ($demandes as $demande) {
            switch ($demande->getStatut()) {
                case 'en_attente':
                    $stats['en_attente']++;
                    break;
                case 'en_cours':
                    $stats['en_cours']++;
                    break;
                case 'termine':
                    $stats['termine']++;
                    if ($demande->getEnvoyeLe()) {
                        $delai = $demande->getDateDemande()->diff($demande->getEnvoyeLe())->days;
                        $totalDelai += $delai;
                        $nbTermines++;
                    }
                    break;
                case 'annule':
                    $stats['annule']++;
                    break;
            }
            
            switch ($demande->getPriorite()) {
                case 'Haute':
                    $stats['priorite_haute']++;
                    break;
                case 'Moyenne':
                    $stats['priorite_moyenne']++;
                    break;
                default:
                    $stats['priorite_normale']++;
                    break;
            }
            
            $mois = $demande->getDateDemande()->format('m/Y');
            if (!isset($stats['demandes_par_mois'][$mois])) {
                $stats['demandes_par_mois'][$mois] = 0;
            }
            $stats['demandes_par_mois'][$mois]++;
            
            $laboratoire = $demande->getLaboratoire();
            if ($laboratoire) {
                $labId = $laboratoire->getId();
                if (!isset($laboratoiresCount[$labId])) {
                    $laboratoiresCount[$labId] = [
                        'nom' => $laboratoire->getNom(),
                        'count' => 0
                    ];
                }
                $laboratoiresCount[$labId]['count']++;
            }
            
            $patient = $demande->getPatient();
            if ($patient) {
                $patientId = $patient->getId();
                if (!isset($patientsCount[$patientId])) {
                    $patientsCount[$patientId] = [
                        'id' => $patientId,
                        'nom' => $patient->getNom(),
                        'prenom' => $patient->getPrenom(),
                        'count' => 0
                    ];
                }
                $patientsCount[$patientId]['count']++;
            }
        }
        
        $stats['delai_moyen_traitement'] = $nbTermines > 0 ? round($totalDelai / $nbTermines, 1) : 0;
        $stats['total_patients'] = count($patientsCount);
        
        $stats['demandes_par_mois'] = array_map(function($mois, $count) {
            return ['mois' => $mois, 'count' => $count];
        }, array_keys($stats['demandes_par_mois']), array_values($stats['demandes_par_mois']));
        
        usort($stats['demandes_par_mois'], function($a, $b) {
            return strtotime('01/' . $a['mois']) <=> strtotime('01/' . $b['mois']);
        });
        
        $stats['demandes_par_laboratoire'] = array_values($laboratoiresCount);
        usort($stats['demandes_par_laboratoire'], fn($a, $b) => $b['count'] <=> $a['count']);
        
        $stats['top_patients'] = array_values($patientsCount);
        usort($stats['top_patients'], fn($a, $b) => $b['count'] <=> $a['count']);
        $stats['top_patients'] = array_slice($stats['top_patients'], 0, 5);
        
        if ($stats['total'] > 0) {
            $stats['pourcentage_attente'] = round($stats['en_attente'] / $stats['total'] * 100, 1);
            $stats['pourcentage_cours'] = round($stats['en_cours'] / $stats['total'] * 100, 1);
            $stats['pourcentage_termine'] = round($stats['termine'] / $stats['total'] * 100, 1);
            $stats['pourcentage_annule'] = round($stats['annule'] / $stats['total'] * 100, 1);
        } else {
            $stats['pourcentage_attente'] = 0;
            $stats['pourcentage_cours'] = 0;
            $stats['pourcentage_termine'] = 0;
            $stats['pourcentage_annule'] = 0;
        }
        
        return $this->render('admin/demande_analyse/stats.html.twig', [
            'stats' => $stats
        ]);
    }

    // ==================== STATISTIQUES DES FICHES MÃ‰DICALES ====================

    #[Route('/statistiques/fiches-medicales', name: 'medical_records_stats')]
    public function medicalRecordsStats(Request $request, FicheMedicaleRepository $ficheRepo, PatientRepository $patientRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $fiches = $ficheRepo->findAll();
        $totalFiches = count($fiches);

        $stats = [
            'total' => $totalFiches,
            'actives' => 0,
            'inactives' => 0,
            'avec_imc' => 0,
            'sans_imc' => 0,
            'imc_maigreur' => 0,
            'imc_normal' => 0,
            'imc_surpoids' => 0,
            'imc_obesite' => 0,
            'avec_traitement' => 0,
            'sans_traitement' => 0,
            'avec_diagnostic' => 0,
            'sans_diagnostic' => 0,
            'imc_moyen' => 0,
            'imc_min' => 100,
            'imc_max' => 0,
            'top_patients' => [],
            'creations_par_mois' => [],
            'pourcentage_actives' => 0,
            'pourcentage_inactives' => 0,
            'pourcentage_avec_imc' => 0,
            'pourcentage_maigreur' => 0,
            'pourcentage_normal' => 0,
            'pourcentage_surpoids' => 0,
            'pourcentage_obesite' => 0,
        ];

        $patientCounts = [];
        $totalImc = 0;

        foreach ($fiches as $fiche) {
            if ($fiche->getStatut() === 'actif') {
                $stats['actives']++;
            } else {
                $stats['inactives']++;
            }

            $imc = $fiche->getImc();
            if ($imc) {
                $stats['avec_imc']++;
                $totalImc += $imc;
                $stats['imc_min'] = min($stats['imc_min'], $imc);
                $stats['imc_max'] = max($stats['imc_max'], $imc);

                if ($imc < 18.5) {
                    $stats['imc_maigreur']++;
                } elseif ($imc < 25) {
                    $stats['imc_normal']++;
                } elseif ($imc < 30) {
                    $stats['imc_surpoids']++;
                } else {
                    $stats['imc_obesite']++;
                }
            } else {
                $stats['sans_imc']++;
            }

            if ($fiche->getTraitementPrescrit()) {
                $stats['avec_traitement']++;
            } else {
                $stats['sans_traitement']++;
            }

            if ($fiche->getDiagnostic()) {
                $stats['avec_diagnostic']++;
            } else {
                $stats['sans_diagnostic']++;
            }

            $patient = $fiche->getPatient();
            if ($patient) {
                $patientId = $patient->getId();
                if (!isset($patientCounts[$patientId])) {
                    $patientCounts[$patientId] = [
                        'id' => $patientId,
                        'prenom' => $patient->getPrenom(),
                        'nom' => $patient->getNom(),
                        'count' => 0
                    ];
                }
                $patientCounts[$patientId]['count']++;
            }

            $dateCreation = $fiche->getCreeLe();
            if ($dateCreation) {
                $mois = $dateCreation->format('m/Y');
                if (!isset($stats['creations_par_mois'][$mois])) {
                    $stats['creations_par_mois'][$mois] = 0;
                }
                $stats['creations_par_mois'][$mois]++;
            }
        }

        if ($stats['avec_imc'] > 0) {
            $stats['imc_moyen'] = round($totalImc / $stats['avec_imc'], 1);
        }

        if ($stats['avec_imc'] == 0) {
            $stats['imc_min'] = 0;
        }

        usort($patientCounts, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        $stats['top_patients'] = array_slice($patientCounts, 0, 5);

        $creationsParMois = [];
        foreach ($stats['creations_par_mois'] as $mois => $count) {
            $creationsParMois[] = [
                'mois' => $mois,
                'count' => $count
            ];
        }
        $stats['creations_par_mois'] = $creationsParMois;

        if ($totalFiches > 0) {
            $stats['pourcentage_actives'] = round($stats['actives'] / $totalFiches * 100, 1);
            $stats['pourcentage_inactives'] = round($stats['inactives'] / $totalFiches * 100, 1);
            $stats['pourcentage_avec_imc'] = round($stats['avec_imc'] / $totalFiches * 100, 1);
            
            $totalAvecImc = $stats['avec_imc'];
            if ($totalAvecImc > 0) {
                $stats['pourcentage_maigreur'] = round($stats['imc_maigreur'] / $totalAvecImc * 100, 1);
                $stats['pourcentage_normal'] = round($stats['imc_normal'] / $totalAvecImc * 100, 1);
                $stats['pourcentage_surpoids'] = round($stats['imc_surpoids'] / $totalAvecImc * 100, 1);
                $stats['pourcentage_obesite'] = round($stats['imc_obesite'] / $totalAvecImc * 100, 1);
            }
        }

        return $this->render('admin/statistics/medical_records_stats.html.twig', [
            'stats' => $stats,
        ]);
    }

    // ==================== STATISTIQUES DES RENDEZ-VOUS ====================

    #[Route('/statistiques/rendez-vous', name: 'appointments_stats')]
    public function appointmentsStats(Request $request, RendezVousRepository $rdvRepo, MedecinRepository $medecinRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $period = $request->query->getString('period');
        if ($period === '') {
            $period = 'month';
        }
        $startDate = $this->toNullableString($request->query->getString('start_date'));
        $endDate = $this->toNullableString($request->query->getString('end_date'));

        $dateRanges = $this->getDateRangeForPeriod($period, $startDate, $endDate);
        
        $appointments = $rdvRepo->findByDateRange($dateRanges['start'], $dateRanges['end']);
        
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');
        $todayAppointments = $rdvRepo->findByDateRange($today, $tomorrow);

        $weekStart = $this->getWeekStart();
        $weekEnd = $this->getWeekEnd();
        $weekAppointments = $rdvRepo->findByDateRange($weekStart, $weekEnd);

        $monthStart = $this->getMonthStart();
        $monthEnd = $this->getMonthEnd();
        $monthAppointments = $rdvRepo->findByDateRange($monthStart, $monthEnd);

        $stats = [
            'total' => count($appointments),
            'total_global' => $rdvRepo->count([]),
            
            'confirmes' => $this->countByStatus($appointments, 'ConfirmÃ©'),
            'en_attente' => $this->countByStatus($appointments, 'En attente'),
            'annules' => $this->countByStatus($appointments, 'AnnulÃ©'),
            'termines' => $this->countByStatus($appointments, 'TerminÃ©'),
            
            'aujourdhui' => count($todayAppointments),
            'restants_aujourdhui' => $this->countRemainingToday($todayAppointments),
            'semaine' => count($weekAppointments),
            'mois' => count($monthAppointments),
            
            'pourcentage_confirmes' => 0,
            'pourcentage_attente' => 0,
            'pourcentage_termines' => 0,
            'taux_annulation' => 0,
            
            'top_medecins' => $this->getTopMedecinsSimple($appointments),
            
            'creneaux_horaires' => $this->getHourlyDistribution($appointments),
            'lundi' => 0,
            'mardi' => 0,
            'mercredi' => 0,
            'jeudi' => 0,
            'vendredi' => 0,
            'samedi' => 0,
            'dimanche' => 0,
            'max_jour' => 1,
            'max_creneau' => 1,
            
            'delai_moyen' => $this->calculateAverageDelay($appointments),
            'delai_min' => $this->calculateMinDelay($appointments),
            'delai_max' => $this->calculateMaxDelay($appointments),
            
            'taux_presence' => $this->calculatePresenceRate($appointments),
            'taux_absence' => 0,
            'taux_occupation' => $this->calculateOccupationRate($appointments),
            'evolution_total' => 0,
            
            'duree_moyenne' => 30,
            'periode_actuelle' => $this->getPeriodLabel($period),
            'moyenne_journaliere' => $this->calculateDailyAverage($appointments, $dateRanges),
            'projection_mois' => $this->calculateMonthProjection($rdvRepo),
            'evolution_labels' => $this->generateEvolutionLabels($dateRanges),
            'evolution_data' => $this->generateEvolutionData($appointments, $dateRanges),
        ];

        $dailyDist = $this->getDailyDistribution($appointments);
        foreach ($dailyDist as $jour => $count) {
            $stats[strtolower($jour)] = $count;
        }
        $stats['max_jour'] = !empty($dailyDist) ? max($dailyDist) : 1;

        $hourlyDist = $this->getHourlyDistribution($appointments);
        $creneaux = [];
        foreach ($hourlyDist as $heure => $count) {
            $creneaux[] = ['heure' => $heure, 'nb_rdv' => $count];
        }
        $stats['creneaux_horaires'] = $creneaux;
        $stats['max_creneau'] = !empty($hourlyDist) ? max($hourlyDist) : 1;

        $totalStats = (int) ($stats['total'] ?? 0);
        if ($totalStats > 0) {
            $confirmes = (int) ($stats['confirmes'] ?? 0);
            $enAttente = (int) ($stats['en_attente'] ?? 0);
            $termines = (int) ($stats['termines'] ?? 0);
            $annules = (int) ($stats['annules'] ?? 0);

            $stats['pourcentage_confirmes'] = round($confirmes / $totalStats * 100, 1);
            $stats['pourcentage_attente'] = round($enAttente / $totalStats * 100, 1);
            $stats['pourcentage_termines'] = round($termines / $totalStats * 100, 1);
            $stats['taux_annulation'] = round($annules / $totalStats * 100, 1);
        }

        $stats['taux_absence'] = 100 - (float) ($stats['taux_presence'] ?? 0);

        return $this->render('admin/statistics/appointments_stats.html.twig', [
            'stats' => $stats,
            'current_period' => $period,
            'start_date' => $dateRanges['start']?->format('Y-m-d'),
            'end_date' => $dateRanges['end']?->format('Y-m-d'),
        ]);
    }

    #[Route('/api/statistiques/rendez-vous', name: 'appointments_stats_api')]
    public function appointmentsStatsApi(Request $request, RendezVousRepository $rdvRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $period = $request->query->getString('period');
        if ($period === '') {
            $period = 'month';
        }
        $startDate = $this->toNullableString($request->query->getString('start_date'));
        $endDate = $this->toNullableString($request->query->getString('end_date'));

        $dateRanges = $this->getDateRangeForPeriod($period, $startDate, $endDate);
        
        $appointments = $rdvRepo->findByDateRange($dateRanges['start'], $dateRanges['end']);

        return $this->json([
            'total' => count($appointments),
            'by_status' => [
                'confirmes' => $this->countByStatus($appointments, 'ConfirmÃ©'),
                'en_attente' => $this->countByStatus($appointments, 'En attente'),
                'annules' => $this->countByStatus($appointments, 'AnnulÃ©'),
                'termines' => $this->countByStatus($appointments, 'TerminÃ©'),
            ],
            'evolution' => [
                'labels' => $this->generateEvolutionLabels($dateRanges),
                'data' => $this->generateEvolutionData($appointments, $dateRanges)
            ],
        ]);
    }

    // ==================== ROUTES POUR LES RENDEZ-VOUS ====================

    #[Route('/users/{id}/appointments', name: 'user_appointments', requirements: ['id' => '\d+'])]
    public function userAppointments(int $id, RendezVousRepository $rdvRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $user = $this->userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvÃ©');
        }

        if ($user instanceof Patient) {
            $appointments = $rdvRepo->findBy(['patient' => $user], ['dateRdv' => 'DESC', 'heureRdv' => 'DESC']);
        } elseif ($user instanceof Medecin) {
            $appointments = $rdvRepo->findBy(['medecin' => $user], ['dateRdv' => 'DESC', 'heureRdv' => 'DESC']);
        } else {
            $appointments = [];
        }

        return $this->render('admin/user_appointments.html.twig', [
            'user' => $user,
            'appointments' => $appointments,
        ]);
    }

    #[Route('/appointments/all', name: 'all_appointments')]
    public function allAppointments(RendezVousRepository $rdvRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $appointments = $rdvRepo->findBy([], ['dateRdv' => 'DESC', 'heureRdv' => 'DESC']);

        return $this->render('admin/all_appointments.html.twig', [
            'appointments' => $appointments,
        ]);
    }

    #[Route('/appointments/{id}/update-status', name: 'update_appointment_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateAppointmentStatus(Request $request, int $id, RendezVousRepository $rdvRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $appointment = $rdvRepo->find($id);
        if (!$appointment) {
            $this->addFlash('error', 'Rendez-vous non trouvÃ©');
            return $this->redirectToRoute('admin_all_appointments');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('update-appointment-status' . $appointment->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide');
            return $this->redirectToRoute('admin_view_appointment', ['id' => $id]);
        }

        $newStatus = $request->request->getString('status');
        $appointment->setStatut($newStatus);
        
        if ($newStatus === 'confirmÃ©') {
            $appointment->setDateValidation(new \DateTime());
        }

        $this->em->flush();

        $this->addFlash('success', 'Statut du rendez-vous mis Ã  jour avec succÃ¨s');
        return $this->redirectToRoute('admin_view_appointment', ['id' => $id]);
    }

    // ==================== ROUTES POUR LES FICHES MÃ‰DICALES ====================

    #[Route('/medical-records/all', name: 'all_medical_records')]
    public function allMedicalRecords(FicheMedicaleRepository $ficheRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $records = $ficheRepo->findBy([], ['creeLe' => 'DESC']);

        return $this->render('admin/all_medical_records.html.twig', [
            'records' => $records,
        ]);
    }

    #[Route('/medical-records/{id}', name: 'view_medical_record', requirements: ['id' => '\d+'])]
    public function viewMedicalRecord(int $id, FicheMedicaleRepository $ficheRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $record = $ficheRepo->find($id);
        if (!$record) {
            throw $this->createNotFoundException('Fiche mÃ©dicale non trouvÃ©e');
        }

        if (!$record->getImc() && $record->getTaille() && $record->getPoids()) {
            $record->calculerImc();
        }

        return $this->render('admin/view_medical_record.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/medical-records/{id}/edit', name: 'edit_medical_record', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editMedicalRecord(Request $request, int $id, FicheMedicaleRepository $ficheRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $record = $ficheRepo->find($id);
        if (!$record) {
            throw $this->createNotFoundException('Fiche mÃ©dicale non trouvÃ©e');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('edit-medical-record' . $record->getId(), $token)) {
                $this->addFlash('error', 'Jeton CSRF invalide');
                return $this->redirectToRoute('admin_view_medical_record', ['id' => $id]);
            }

            $data = $request->request;
            
            $record->setTaille($this->toNullableString($data->getString('taille')));
            $record->setPoids($this->toNullableString($data->getString('poids')));
            $record->setAntecedents($this->toNullableString($data->getString('antecedents')));
            $record->setAllergies($this->toNullableString($data->getString('allergies')));
            $record->setTraitementEnCours($this->toNullableString($data->getString('traitement_en_cours')));
            $record->setDiagnostic($this->toNullableString($data->getString('diagnostic')));
            $record->setTraitementPrescrit($this->toNullableString($data->getString('traitement_prescrit')));
            $record->setObservations($this->toNullableString($data->getString('observations')));
            
            $record->calculerImc();

            $this->em->flush();

            $this->addFlash('success', 'Fiche mÃ©dicale mise Ã  jour avec succÃ¨s');
            return $this->redirectToRoute('admin_view_medical_record', ['id' => $id]);
        }

        return $this->render('admin/edit_medical_record.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/patients/{patientId}/add-medical-record', name: 'add_medical_record', methods: ['POST'], requirements: ['patientId' => '\d+'])]
    public function addMedicalRecord(Request $request, int $patientId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $patient = $this->userRepo->find($patientId);
        if (!$patient || !$patient instanceof Patient) {
            $this->addFlash('error', 'Patient non trouvÃ©');
            return $this->redirectToRoute('admin_index');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('add-medical-record' . $patient->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide');
            return $this->redirectToRoute('admin_index');
        }

        $data = $request->request;
        
        $record = new FicheMedicale();
        $record->setPatient($patient);
        $record->setTaille($this->toNullableString($data->getString('taille')));
        $record->setPoids($this->toNullableString($data->getString('poids')));
        $record->setAntecedents($this->toNullableString($data->getString('antecedents')));
        $record->setAllergies($this->toNullableString($data->getString('allergies')));
        $record->setTraitementEnCours($this->toNullableString($data->getString('traitement_en_cours')));
        $record->setDiagnostic($this->toNullableString($data->getString('diagnostic')));
        $record->setTraitementPrescrit($this->toNullableString($data->getString('traitement_prescrit')));
        $record->setObservations($this->toNullableString($data->getString('observations')));
        $record->setStatut('actif');
        
        $record->calculerImc();

        $this->em->persist($record);
        $this->em->flush();

        $this->addFlash('success', 'Fiche mÃ©dicale ajoutÃ©e avec succÃ¨s');
        return $this->redirectToRoute('admin_patient_medical_records', ['id' => $patientId]);
    }

    #[Route('/appointments/{appointmentId}/add-medical-record', name: 'add_medical_record_from_appointment', methods: ['POST'], requirements: ['appointmentId' => '\d+'])]
    public function addMedicalRecordFromAppointment(
        Request $request, 
        int $appointmentId,
        RendezVousRepository $rdvRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $appointment = $rdvRepo->find($appointmentId);
        if (!$appointment) {
            $this->addFlash('error', 'Rendez-vous non trouvÃ©');
            return $this->redirectToRoute('admin_all_appointments');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('add-medical-record-appointment' . $appointment->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide');
            return $this->redirectToRoute('admin_view_appointment', ['id' => $appointmentId]);
        }

        if ($appointment->getFicheMedicale()) {
            $this->addFlash('warning', 'Ce rendez-vous a dÃ©jÃ  une fiche mÃ©dicale');
            return $this->redirectToRoute('admin_view_medical_record', ['id' => $appointment->getFicheMedicale()->getId()]);
        }

        $data = $request->request;
        
        $record = new FicheMedicale();
        $record->setPatient($appointment->getPatient());
        $record->setRendezVous($appointment);
        $record->setTaille($this->toNullableString($data->getString('taille')));
        $record->setPoids($this->toNullableString($data->getString('poids')));
        $record->setDiagnostic($this->toNullableString($data->getString('diagnostic')));
        $record->setTraitementPrescrit($this->toNullableString($data->getString('traitement_prescrit')));
        $record->setObservations($this->toNullableString($data->getString('observations')));
        $record->setStatut('actif');
        
        $record->calculerImc();

        $appointment->setFicheMedicale($record);

        $this->em->persist($record);
        $this->em->flush();

        $this->addFlash('success', 'Fiche mÃ©dicale crÃ©Ã©e et liÃ©e au rendez-vous avec succÃ¨s');
        return $this->redirectToRoute('admin_view_medical_record', ['id' => $record->getId()]);
    }

    #[Route('/medical-records/{id}/export-pdf', name: 'export_medical_record_pdf', requirements: ['id' => '\d+'])]
    public function exportMedicalRecordPdf(int $id, FicheMedicaleRepository $ficheRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $record = $ficheRepo->find($id);
        if (!$record) {
            throw $this->createNotFoundException('Fiche mÃ©dicale non trouvÃ©e');
        }

        if (!$record->getImc() && $record->getTaille() && $record->getPoids()) {
            $record->calculerImc();
        }

        return $this->render('admin/medical_record_pdf.html.twig', [
            'record' => $record,
        ]);
    }

    // ==================== ROUTES POUR LES PATIENTS ====================

    #[Route('/patients/{id}/medical-records', name: 'patient_medical_records', requirements: ['id' => '\d+'])]
    public function patientMedicalRecords(int $id, FicheMedicaleRepository $ficheRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $patient = $this->userRepo->find($id);
        if (!$patient || !$patient instanceof Patient) {
            throw $this->createNotFoundException('Patient non trouvÃ©');
        }

        $records = $ficheRepo->findBy(['patient' => $patient], ['creeLe' => 'DESC']);

        return $this->render('admin/patient_medical_records.html.twig', [
            'patient' => $patient,
            'records' => $records,
        ]);
    }

    #[Route('/patients', name: 'patients')]
    public function patients(PatientRepository $patientRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $patients = $patientRepo->findAll();

        return $this->render('admin/patients.html.twig', [
            'patients' => $patients,
        ]);
    }

    // ==================== ROUTES POUR LES UTILISATEURS ====================

    #[Route('/users', name: 'users')]
    public function users(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $role = $this->toNullableString($request->query->getString('role'));
        $search = $this->toNullableString($request->query->getString('search'));
        
        if ($search || $role) {
            $utilisateurs = $this->userRepo->search($search, $role);
        } else {
            $utilisateurs = $this->userRepo->findAll();
        }

        return $this->render('admin/users.html.twig', [
            'utilisateurs' => $utilisateurs,
            'selectedRole' => $role,
            'searchQuery' => $search
        ]);
    }

    #[Route('/users/search', name: 'users_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $role = $this->toNullableString($request->query->getString('role'));
        $search = $this->toNullableString($request->query->getString('search'));
        
        if ($search || $role) {
            $utilisateurs = $this->userRepo->search($search, $role);
        } else {
            $utilisateurs = $this->userRepo->findAll();
        }
        
        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/_user_table_rows.html.twig', [
                'utilisateurs' => $utilisateurs
            ]);
        }
        
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/new', name: 'user_new')]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('user_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_user_new');
            }

            $data = $request->request;

            $role = $data->getString('role');
            switch ($role) {
                case 'medecin':
                    $user = new Medecin();
                    break;
                case 'patient':
                    $user = new Patient();
                    break;
                case 'responsable_labo':
                    $user = new ResponsableLaboratoire();
                    break;
                case 'responsable_para':
                    $user = new ResponsableParapharmacie();
                    break;
                default:
                    $user = new Utilisateur();
            }

            $user->setNom($data->getString('nom'));
            $user->setPrenom($data->getString('prenom'));
            $user->setEmail($data->getString('email'));
            if ($role !== '') {
                $user->setRole($role);
            } elseif ($user->getRole() !== null) {
                $user->setRole($user->getRole());
            }

            if (($tel = $this->toNullableString($data->getString('telephone'))) !== null) {
                $user->setTelephone($tel);
            }
            if (($dn = $this->toNullableString($data->getString('dateNaissance'))) !== null) {
                try {
                    $user->setDateNaissance(new \DateTime($dn));
                } catch (\Exception $e) {
                }
            }
            $user->setEstActif($request->request->has('estActif'));

            $plain = $data->getString('password');
            if ($plain !== '') {
                $hashed = $passwordHasher->hashPassword($user, $plain);
                $user->setPassword($hashed);
            }

            $photo = $request->files->get('photoUpload');
            if ($photo instanceof UploadedFile) {
                $uploadsDir = $this->getProjectDir() . '/public/uploads/profiles';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0777, true);
                }
                $filename = uniqid('profile_') . '.' . $photo->guessExtension();
                $photo->move($uploadsDir, $filename);
                $user->setPhotoProfil('/uploads/profiles/' . $filename);
            } elseif (($photoProfil = $this->toNullableString($data->getString('photoProfil'))) !== null) {
                $user->setPhotoProfil($photoProfil);
            }

            if ($user instanceof Medecin) {
                $user->setSpecialite($this->toNullableString($data->getString('specialite')));
                $exp = $this->toNullableString($data->getString('anneeExperience'));
                $user->setAnneeExperience($exp !== null ? (int) $exp : null);
                $user->setGrade($this->toNullableString($data->getString('grade')));
                $user->setAdresseCabinet($this->toNullableString($data->getString('adresseCabinet')));
                $user->setTelephoneCabinet($this->toNullableString($data->getString('telephoneCabinet')));
                $user->setNomEtablissement($this->toNullableString($data->getString('nomEtablissement')));
                $user->setNumeroUrgence($this->toNullableString($data->getString('numeroUrgence')));
                $user->setDisponibilite($this->toNullableString($data->getString('disponibilite')));

                $doc = $request->files->get('documentPdf');
                if ($doc instanceof UploadedFile) {
                    $uploadsDir = $this->getProjectDir() . '/public/uploads/docs';
                    if (!is_dir($uploadsDir)) {
                        @mkdir($uploadsDir, 0777, true);
                    } 
                    $docName = uniqid('doc_') . '.' . $doc->guessExtension();
                    $doc->move($uploadsDir, $docName);
                    $user->setDocumentPdf('/uploads/docs/' . $docName);
                }
            }

            if ($user instanceof Patient) {
                $user->setGroupeSanguin($this->toNullableString($data->getString('groupeSanguin')));
                $user->setContactUrgence($this->toNullableString($data->getString('contactUrgence')));
                $user->setSexe($this->toNullableString($data->getString('sexe')));
            }

            if ($user instanceof ResponsableLaboratoire) {
                $laboratoireId = $data->get('laboratoire_id');
                if ($laboratoireId) {
                    $laboratoire = $this->em->getRepository(Laboratoire::class)->find($laboratoireId);
                    $user->setLaboratoire($laboratoire);
                }
            }

            if ($user instanceof ResponsableParapharmacie) {
                $user->setParapharmacieId($data->get('parapharmacieId') ? (int)$data->get('parapharmacieId') : null);
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Utilisateur crÃ©Ã©.');
            return $this->redirectToRoute('admin_users');
        }

        $laboratoires = $this->em->getRepository(Laboratoire::class)->findBy(['disponible' => true], ['nom' => 'ASC']);

        return $this->render('admin/user_form.html.twig', [
            'user' => null,
            'laboratoires' => $laboratoires,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id, UserPasswordHasherInterface $passwordHasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->getString('_token');
            if (!$this->isCsrfTokenValid('user_form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
            }

            $data = $request->request;
            $user->setNom($data->getString('nom'));
            $user->setPrenom($data->getString('prenom'));
            $user->setEmail($data->getString('email'));
            $user->setRole($data->getString('role'));

            if (($tel = $this->toNullableString($data->getString('telephone'))) !== null) {
                $user->setTelephone($tel);
            } else {
                $user->setTelephone(null);
            }

            if (($dn = $this->toNullableString($data->getString('dateNaissance'))) !== null) {
                try {
                    $user->setDateNaissance(new \DateTime($dn));
                } catch (\Exception $e) {
                }
            } else {
                $user->setDateNaissance(null);
            }

            $user->setEstActif($request->request->has('estActif'));

            $plain = $data->getString('password');
            if ($plain !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            $photo = $request->files->get('photoUpload');
            if ($photo instanceof UploadedFile) {
                $uploadsDir = $this->getProjectDir() . '/public/uploads/profiles';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0777, true);
                } 
                $filename = uniqid('profile_') . '.' . $photo->guessExtension();
                $photo->move($uploadsDir, $filename);
                $user->setPhotoProfil('/uploads/profiles/' . $filename);
            } elseif (($photoProfil = $this->toNullableString($data->getString('photoProfil'))) !== null) {
                $user->setPhotoProfil($photoProfil);
            }

            if ($user instanceof Medecin) {
                $user->setSpecialite($this->toNullableString($data->getString('specialite')));
                $exp = $this->toNullableString($data->getString('anneeExperience'));
                $user->setAnneeExperience($exp !== null ? (int) $exp : null);
                $user->setGrade($this->toNullableString($data->getString('grade')));
                $user->setAdresseCabinet($this->toNullableString($data->getString('adresseCabinet')));
                $user->setTelephoneCabinet($this->toNullableString($data->getString('telephoneCabinet')));
                $user->setNomEtablissement($this->toNullableString($data->getString('nomEtablissement')));
                $user->setNumeroUrgence($this->toNullableString($data->getString('numeroUrgence')));
                $user->setDisponibilite($this->toNullableString($data->getString('disponibilite')));

                $doc = $request->files->get('documentPdf');
                if ($doc instanceof UploadedFile) {
                    $uploadsDir = $this->getProjectDir() . '/public/uploads/docs';
                    if (!is_dir($uploadsDir)) {
                        @mkdir($uploadsDir, 0777, true);
                    } 
                    $docName = uniqid('doc_') . '.' . $doc->guessExtension();
                    $doc->move($uploadsDir, $docName);
                    $user->setDocumentPdf('/uploads/docs/' . $docName);
                }
            }

            if ($user instanceof Patient) {
                $user->setGroupeSanguin($this->toNullableString($data->getString('groupeSanguin')));
                $user->setContactUrgence($this->toNullableString($data->getString('contactUrgence')));
                $user->setSexe($this->toNullableString($data->getString('sexe')));
            }

            if ($user instanceof ResponsableLaboratoire) {
                $laboratoireId = $data->get('laboratoire_id');
                if ($laboratoireId) {
                    $laboratoire = $this->em->getRepository(Laboratoire::class)->find($laboratoireId);
                    $user->setLaboratoire($laboratoire);
                } else {
                    $user->setLaboratoire(null);
                }
            }

            if ($user instanceof ResponsableParapharmacie) {
                $user->setParapharmacieId($data->get('parapharmacieId') ? (int)$data->get('parapharmacieId') : null);
            }

            $this->em->flush();
            $this->addFlash('success', 'Utilisateur mis Ã  jour.');
            return $this->redirectToRoute('admin_users');
        }

        $laboratoires = $this->em->getRepository(Laboratoire::class)->findBy(['disponible' => true], ['nom' => 'ASC']);

        return $this->render('admin/user_form.html.twig', [
            'user' => $user,
            'laboratoires' => $laboratoires,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('delete-user' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_users');
        }

        $this->em->remove($user);
        $this->em->flush();

        $this->addFlash('success', 'Utilisateur supprimÃ©.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/toggle-status', name: 'user_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas dÃ©sactiver votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('toggle-user' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setEstActif(!$user->isEstActif());
        $this->em->flush();

        $status = $user->isEstActif() ? 'activÃ©' : 'dÃ©sactivÃ©';
        $this->addFlash('success', "Utilisateur $status.");
        return $this->redirectToRoute('admin_users');
    }

    // ==================== MÃ‰THODES UTILITAIRES ====================

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
     * @return array{start: \DateTime|null, end: \DateTime|null}
     */
    private function getDateRangeForPeriod(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        $now = new \DateTime();
        $result = ['start' => null, 'end' => null];

        switch ($period) {
            case 'today':
                $result['start'] = (new \DateTime())->setTime(0, 0, 0);
                $result['end'] = (new \DateTime())->setTime(23, 59, 59);
                break;

            case 'week':
                $result['start'] = (new \DateTime())->modify('monday this week')->setTime(0, 0, 0);
                $result['end'] = (new \DateTime())->modify('sunday this week')->setTime(23, 59, 59);
                break;

            case 'month':
                $result['start'] = (new \DateTime())->modify('first day of this month')->setTime(0, 0, 0);
                $result['end'] = (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59);
                break;

            case 'year':
                $year = (int) $now->format('Y');
                $result['start'] = (new \DateTime())->setDate($year, 1, 1)->setTime(0, 0, 0);
                $result['end'] = (new \DateTime())->setDate($year, 12, 31)->setTime(23, 59, 59);
                break;

            case 'custom':
                if ($startDate && $endDate) {
                    $result['start'] = new \DateTime($startDate);
                    $result['start']->setTime(0, 0, 0);
                    $result['end'] = new \DateTime($endDate);
                    $result['end']->setTime(23, 59, 59);
                }
                break;

            default:
                $result['start'] = (new \DateTime())->modify('first day of this month')->setTime(0, 0, 0);
                $result['end'] = (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59);
        }

        return $result;
    }

    private function getWeekStart(): \DateTime
    {
        return (new \DateTime())->modify('monday this week')->setTime(0, 0, 0);
    }

    private function getWeekEnd(): \DateTime
    {
        return (new \DateTime())->modify('sunday this week')->setTime(23, 59, 59);
    }

    private function getMonthStart(): \DateTime
    {
        return (new \DateTime())->modify('first day of this month')->setTime(0, 0, 0);
    }

    private function getMonthEnd(): \DateTime
    {
        return (new \DateTime())->modify('last day of this month')->setTime(23, 59, 59);
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function countByStatus(array $appointments, string $status): int
    {
        $count = 0;
        foreach ($appointments as $appointment) {
            if ($appointment->getStatut() === $status) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function countRemainingToday(array $appointments): int
    {
        $now = new \DateTime();
        $count = 0;
        
        foreach ($appointments as $appointment) {
            $heureRdv = $appointment->getHeureRdv();
            if ($heureRdv && $heureRdv > $now) {
                $count++;
            }
        }
        
        return $count;
    }

    private function getPeriodLabel(string $period): string
    {
        switch ($period) {
            case 'today': return 'Aujourd\'hui';
            case 'week': return 'Cette semaine';
            case 'month': return 'Ce mois';
            case 'year': return 'Cette annÃ©e';
            case 'custom': return 'PÃ©riode personnalisÃ©e';
            default: return 'Cette pÃ©riode';
        }
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function calculatePresenceRate(array $appointments): int
    {
        $total = 0;
        $present = 0;

        foreach ($appointments as $appointment) {
            $statut = $appointment->getStatut();
            if ($statut === 'ConfirmÃ©' || $statut === 'TerminÃ©') {
                $present++;
                $total++;
            } elseif ($statut === 'AnnulÃ©') {
                $total++;
            }
        }

        return $total > 0 ? (int)round($present / $total * 100) : 0;
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function calculateAverageDelay(array $appointments): float
    {
        $totalDelay = 0;
        $count = 0;

        foreach ($appointments as $appointment) {
            $dateCreation = $appointment->getCreeLe();
            $dateRdv = $appointment->getDateRdv();
            
            if ($dateCreation && $dateRdv && $dateRdv > $dateCreation) {
                $delay = $dateCreation->diff($dateRdv)->days;
                $totalDelay += $delay;
                $count++;
            }
        }

        return $count > 0 ? round($totalDelay / $count, 1) : 0;
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function calculateMinDelay(array $appointments): int
    {
        $min = PHP_INT_MAX;
        foreach ($appointments as $appointment) {
            $dateCreation = $appointment->getCreeLe();
            $dateRdv = $appointment->getDateRdv();
            if ($dateCreation && $dateRdv && $dateRdv > $dateCreation) {
                $days = $dateCreation->diff($dateRdv)->days;
                if (is_int($days)) {
                    $min = min($min, $days);
                }
            }
        }
        return $min != PHP_INT_MAX ? $min : 0;
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function calculateMaxDelay(array $appointments): int
    {
        $max = 0;
        foreach ($appointments as $appointment) {
            $dateCreation = $appointment->getCreeLe();
            $dateRdv = $appointment->getDateRdv();
            if ($dateCreation && $dateRdv && $dateRdv > $dateCreation) {
                $max = max($max, $dateCreation->diff($dateRdv)->days);
            }
        }
        return $max;
    }

    /**
     * @param array<RendezVous> $appointments
     */
    private function calculateOccupationRate(array $appointments): int
    {
        if (empty($appointments)) return 0;
        $totalMinutes = count($appointments) * 30;
        $capaciteMax = 480 * 30;
        return (int) min(100, round(($totalMinutes / $capaciteMax) * 100));
    }

    /**
     * @param array<RendezVous> $appointments
     * @param array{start: \DateTime|null, end: \DateTime|null} $dateRanges
     */
    private function calculateDailyAverage(array $appointments, array $dateRanges): int
    {
        $start = $dateRanges['start'];
        $end = $dateRanges['end'];
        if (!$start || !$end) return 0;
        
        $days = $start->diff($end)->days + 1;
        return ($days > 0 && count($appointments) > 0) ? (int)round(count($appointments) / $days) : 0;
    }

    private function calculateMonthProjection(RendezVousRepository $rdvRepo): int
    {
        $now = new \DateTime();
        $monthStart = $this->getMonthStart();
        $monthEnd = $this->getMonthEnd();
        
        $count = $rdvRepo->countByDateRange($monthStart, $monthEnd);
        $daysPassed = (int)$now->format('j');
        $daysInMonth = (int)$now->format('t');

        return (int) round($count / $daysPassed * $daysInMonth);
    }

    /**
     * @return list<int>
     */
    private function getMonthlyAppointmentsData(RendezVousRepository $rdvRepo): array
    {
        $data = array_fill(0, 12, 0);
        $currentYear = (new \DateTime())->format('Y');
        $start = new \DateTime($currentYear . '-01-01 00:00:00');
        $end = new \DateTime($currentYear . '-12-31 23:59:59');
        
        foreach ($rdvRepo->findByDateRange($start, $end) as $appointment) {
            if ($dateRdv = $appointment->getDateRdv()) {
                $data[(int)$dateRdv->format('n') - 1]++;
            }
        }
        return $data;
    }

    /**
     * @return list<array{name:string,count:int}>
     */
    private function getTopMedecins(RendezVousRepository $rdvRepo, MedecinRepository $medecinRepo): array
    {
        $result = [];
        foreach ($medecinRepo->findAll() as $medecin) {
            if ($count = $rdvRepo->count(['medecin' => $medecin])) {
                $result[] = [
                    'name' => 'Dr. ' . $medecin->getPrenom() . ' ' . $medecin->getNom(),
                    'count' => $count
                ];
            }
        }
        usort($result, fn($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($result, 0, 5);
    }

    /**
     * @param array<RendezVous> $appointments
     * @return list<array{id:int|null,nom:string|null,prenom:string|null,specialite:string,count:int}>
     */
    private function getTopMedecinsSimple(array $appointments): array
    {
        $counts = [];
        foreach ($appointments as $appointment) {
            if ($medecin = $appointment->getMedecin()) {
                $id = $medecin->getId();
                if (!isset($counts[$id])) {
                    $counts[$id] = [
                        'id' => $medecin->getId(),
                        'nom' => $medecin->getNom(),
                        'prenom' => $medecin->getPrenom(),
                        'specialite' => $medecin->getSpecialite() ?? 'GÃ©nÃ©raliste',
                        'count' => 0
                    ];
                }
                $counts[$id]['count']++;
            }
        }
        $countsList = array_values($counts);
        usort($countsList, fn($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($countsList, 0, 5);
    }

    /**
     * @return list<array{name:string,count:int}>
     */
    private function getTopPatients(RendezVousRepository $rdvRepo, PatientRepository $patientRepo): array
    {
        $result = [];
        foreach ($patientRepo->findAll() as $patient) {
            if ($count = $rdvRepo->count(['patient' => $patient])) {
                $result[] = [
                    'name' => $patient->getPrenom() . ' ' . $patient->getNom(),
                    'count' => $count
                ];
            }
        }
        usort($result, fn($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($result, 0, 5);
    }

    /**
     * @return array<string,int>
     */
    private function getImcStatistics(FicheMedicaleRepository $ficheRepo): array
    {
        $stats = ['Maigreur' => 0, 'Normal' => 0, 'Surpoids' => 0, 'ObÃ©sitÃ©' => 0];
        foreach ($ficheRepo->findAll() as $fiche) {
            if ($imc = $fiche->getImc()) {
                if ($imc < 18.5) $stats['Maigreur']++;
                elseif ($imc < 25) $stats['Normal']++;
                elseif ($imc < 30) $stats['Surpoids']++;
                else $stats['ObÃ©sitÃ©']++;
            }
        }
        return $stats;
    }

    /**
     * @return array{mon:int,tue:int,wed:int,thu:int,fri:int,sat:int,sun:int}
     */
    private function getWeeklyAppointmentsData(RendezVousRepository $rdvRepo): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $data = array_fill_keys($days, 0);
        $dayMap = ['Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'];
        
        foreach ($rdvRepo->findByDateRange($this->getWeekStart(), $this->getWeekEnd()) as $appointment) {
            if ($dateRdv = $appointment->getDateRdv()) {
                $dayKey = $dayMap[$dateRdv->format('D')] ?? null;
                if ($dayKey !== null) {
                    $data[$dayKey]++;
                }
            }
        }
        return $data;
    }

    /**
     * @param array<RendezVous> $appointments
     * @return array<string,int>
     */
    private function getHourlyDistribution(array $appointments): array
    {
        $distribution = [];
        for ($h = 8; $h <= 18; $h++) $distribution[$h . 'h'] = 0;

        foreach ($appointments as $appointment) {
            if ($heure = $appointment->getHeureRdv()) {
                $hour = (int)$heure->format('H');
                if ($hour >= 8 && $hour <= 18) $distribution[$hour . 'h']++;
            }
        }
        return $distribution;
    }

    /**
     * @param array<RendezVous> $appointments
     * @return array<string,int>
     */
    private function getDailyDistribution(array $appointments): array
    {
        $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $distribution = array_fill_keys($jours, 0);

        foreach ($appointments as $appointment) {
            if ($dateRdv = $appointment->getDateRdv()) {
                $distribution[$jours[(int)$dateRdv->format('N') - 1]]++;
            }
        }
        return $distribution;
    }

    /**
     * @param array{start: \DateTime|null, end: \DateTime|null} $dateRanges
     * @return list<string>
     */
    private function generateEvolutionLabels(array $dateRanges): array
    {
        $labels = [];
        $start = $dateRanges['start'];
        $end = $dateRanges['end'];

        if (!$start || !$end) {
            return ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        }

        $days = $start->diff($end)->days;
        $current = clone $start;

        if ($days <= 31) {
            while ($current <= $end) {
                $labels[] = $current->format('d/m');
                $current->modify('+1 day');
            }
        } elseif ($days <= 120) {
            while ($current <= $end) {
                $labels[] = 'Sem ' . $current->format('W');
                $current->modify('+1 week');
            }
        } else {
            while ($current <= $end) {
                $labels[] = $current->format('M');
                $current->modify('+1 month');
            }
        }

        return $labels;
    }

    /**
     * @param array<RendezVous> $appointments
     * @param array{start: \DateTime|null, end: \DateTime|null} $dateRanges
     * @return list<int>
     */
    private function generateEvolutionData(array $appointments, array $dateRanges): array
    {
        $start = $dateRanges['start'];
        $end = $dateRanges['end'];

        if (!$start || !$end || empty($appointments)) {
            return [];
        }

        $days = $start->diff($end)->days;

        if ($days <= 31) {
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);
            $data = array_fill(0, iterator_count($period) + 1, 0);
            foreach ($appointments as $appointment) {
                if ($dateRdv = $appointment->getDateRdv()) {
                    $index = (int)$start->diff($dateRdv)->days;
                    if (isset($data[$index])) $data[$index]++;
                }
            }
        } elseif ($days <= 120) {
            $weeks = (int) ceil($days / 7);
            $data = array_fill(0, $weeks, 0);
            foreach ($appointments as $appointment) {
                if ($dateRdv = $appointment->getDateRdv()) {
                    $index = (int) floor($start->diff($dateRdv)->days / 7);
                    if (isset($data[$index])) $data[$index]++;
                }
            }
        } else {
            $data = array_fill(0, 12, 0);
            foreach ($appointments as $appointment) {
                if ($dateRdv = $appointment->getDateRdv()) {
                    $data[(int)$dateRdv->format('n') - 1]++;
                }
            }
        }

        return $data;
    }

    private function toNullableString(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function getProjectDir(): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            throw new \RuntimeException('Invalid kernel.project_dir parameter.');
        }

        return $projectDir;
    }
}

