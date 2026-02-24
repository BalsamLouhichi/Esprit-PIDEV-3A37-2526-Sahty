<?php
// Script simple pour tester les quizzes sans besoin de serveur en cours

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/config/bootstrap.php');

use App\Entity\Quiz;
use Doctrine\ORM\EntityManager;

// Get Entity Manager
$em = $GLOBALS['kernel']->getContainer()->get('doctrine')->getManager();
$quizRepository = $em->getRepository(Quiz::class);

// Get all quizzes
$quizzes = $quizRepository->findAll();

echo "=== QUIZ DEBUG ===\n\n";
echo "Total quizzes: " . count($quizzes) . "\n\n";

if (count($quizzes) > 0) {
    foreach ($quizzes as $quiz) {
        echo "ID: " . $quiz->getId() . "\n";
        echo "Name: " . $quiz->getName() . "\n";
        echo "Description: " . substr($quiz->getDescription() ?? '', 0, 50) . "...\n";
        echo "Questions: " . count($quiz->getQuestions()) . "\n";
        echo "Created: " . $quiz->getCreatedAt()?->format('Y-m-d H:i:s') . "\n";
        echo "---\n\n";
    }
} else {
    echo "❌ NO QUIZZES FOUND IN DATABASE\n";
    echo "\nYou need to create quizzes first!\n";
}
