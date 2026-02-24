<?php

namespace App\Controller;

use App\Repository\QuizRepository;
use App\Repository\RecommandationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DebugController extends AbstractController
{
    #[Route('/debug/quizzes', name: 'debug_quizzes', methods: ['GET'])]
    public function debugQuizzes(QuizRepository $quizRepository): Response
    {
        $allQuizzes = $quizRepository->findAll();
        
        $html = '<h1 style="color: #333; font-family: Arial, sans-serif; margin: 20px;">📊 DEBUG: Quizzes</h1>';
        $html .= '<div style="font-family: Arial, sans-serif; margin: 20px; padding: 20px; background: #f5f5f5; border-radius: 8px;">';
        $html .= '<p><strong>Total de quizzes dans la BD:</strong> <span style="color: #0066cc; font-size: 20px;"><strong>' . count($allQuizzes) . '</strong></span></p>';
        $html .= '<hr>';
        
        if (count($allQuizzes) > 0) {
            $html .= '<h2>Détails des quizzes:</h2>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<tr style="background: #0066cc; color: white;">';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">ID</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Nom</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Description</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Questions</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Créé le</th>';
            $html .= '</tr>';
            
            foreach ($allQuizzes as $idx => $quiz) {
                $bg = $idx % 2 == 0 ? '#fff' : '#f9f9f9';
                $html .= '<tr style="background: ' . $bg . ';">';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($quiz->getId()) . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($quiz->getName()) . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars(substr($quiz->getDescription() ?? '', 0, 50)) . '...</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . count($quiz->getQuestions()) . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . ($quiz->getCreatedAt()?->format('Y-m-d H:i') ?? 'N/A') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '<br><p style="color: green;"><strong>✅ Les quizzes existent!</strong></p>';
            $html .= '<p><a href="/quizzes" style="color: #0066cc; text-decoration: none; font-weight: bold;">➜ Voir la page des quizzes →</a></p>';
        } else {
            $html .= '<div style="background: #fff3cd; padding: 15px; border: 2px solid #ffc107; border-radius: 5px; color: #856404;">';
            $html .= '<p><strong>❌ PROBLÈME DÉTECTÉ:</strong></p>';
            $html .= '<p>Aucun quiz trouvé dans la base de données!</p>';
            $html .= '<p><strong>Solutions:</strong></p>';
            $html .= '<ol>';
            $html .= '<li><strong>Créez des quizzes</strong> via le panneau admin (/admin)</li>';
            $html .= '<li>Vérifiez que la base de données "sahty1" existe</li>';
            $html .= '<li>Vérifiez les migrations avec: <code>php bin/console doctrine:migrations:status</code></li>';
            $html .= '</ol>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return new Response($html);
    }

    #[Route('/debug/recommandations', name: 'debug_recommandations', methods: ['GET'])]
    public function debugRecommandations(RecommandationRepository $recommandationRepository): Response
    {
        $allRecommandations = $recommandationRepository->findAll();
        
        $html = '<h1 style="color: #333; font-family: Arial, sans-serif; margin: 20px;">💡 DEBUG: Recommandations</h1>';
        $html .= '<div style="font-family: Arial, sans-serif; margin: 20px; padding: 20px; background: #f5f5f5; border-radius: 8px;">';
        $html .= '<p><strong>Total de recommandations dans la BD:</strong> <span style="color: #0066cc; font-size: 20px;"><strong>' . count($allRecommandations) . '</strong></span></p>';
        $html .= '<hr>';
        
        if (count($allRecommandations) > 0) {
            $html .= '<h2>Détails des recommandations:</h2>';
            $html .= '<table style="width: 100%; border-collapse: collapse;">';
            $html .= '<tr style="background: #28a745; color: white;">';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">ID</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Nom</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Quiz</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Score Min</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Score Max</th>';
            $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Type</th>';
            $html .= '</tr>';
            
            foreach ($allRecommandations as $idx => $reco) {
                $bg = $idx % 2 == 0 ? '#fff' : '#f9f9f9';
                $html .= '<tr style="background: ' . $bg . ';">';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($reco->getId()) . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($reco->getName()) . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($reco->getQuiz()?->getName() ?? 'N/A') . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $reco->getMinScore() . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . $reco->getMaxScore() . '</td>';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars(substr($reco->getTypeProbleme(), 0, 30)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
            $html .= '<br><p style="color: green;"><strong>✅ Les recommandations existent!</strong></p>';
            $html .= '<p><a href="/recommandation/recommandations" style="color: #0066cc; text-decoration: none; font-weight: bold;">➜ Voir la page des recommandations →</a></p>';
        } else {
            $html .= '<div style="background: #fff3cd; padding: 15px; border: 2px solid #ffc107; border-radius: 5px; color: #856404;">';
            $html .= '<p><strong>ℹ️ INFORMATION:</strong></p>';
            $html .= '<p>Aucune recommandation créée pour le moment.</p>';
            $html .= '<p><strong>Comment créer des recommandations:</strong></p>';
            $html .= '<ol>';
            $html .= '<li>Créez d\'abord des <strong>quizzes</strong></li>';
            $html .= '<li>Puis créez des <strong>recommandations</strong> liées à ces quizzes</li>';
            $html .= '<li>Les recommandations s\'afficheront après avoir complété un quiz</li>';
            $html .= '</ol>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return new Response($html);
    }

    #[Route('/debug/routes', name: 'debug_routes', methods: ['GET'])]
    public function debugRoutes(): Response
    {
        $html = '<h1 style="font-family: Arial, sans-serif; margin: 20px;">🛣️ Routes disponibles</h1>';
        $html .= '<div style="font-family: monospace; margin: 20px; padding: 20px; background: #f5f5f5; border-radius: 8px;">';
        $html .= '<p>✅ GET <strong>/quizzes</strong> - Page des quizzes front (app_quiz_front_list)</p>';
        $html .= '<p>✅ GET <strong>/recommandation/recommandations</strong> - Page des recommandations front (app_recommandation_front_list)</p>';
        $html .= '<p>✅ GET <strong>/quiz/{id}</strong> - Afficher un quiz spécifique (app_quiz_show)</p>';
        $html .= '<p>✅ POST <strong>/quiz/{id}/submit</strong> - Soumettre les réponses (app_quiz_submit)</p>';
        $html .= '<p>✅ GET <strong>/admin/quizzes</strong> - Liste des quizzes admin (admin_quiz_list)</p>';
        $html .= '<p>✅ GET <strong>/admin/quiz/new</strong> - Créer un quiz (app_quiz_new)</p>';
        $html .= '<p>✅ GET <strong>/debug/quizzes</strong> - Diagnostic quizzes</p>';
        $html .= '<p>✅ GET <strong>/debug/recommandations</strong> - Diagnostic recommandations</p>';
        $html .= '</div>';
        
        return new Response($html);
    }
}


