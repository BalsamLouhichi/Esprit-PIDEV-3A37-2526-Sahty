<?php

namespace App\Tests\Service;

use App\Entity\Quiz;
use App\Entity\Question;
use App\Entity\Recommandation;
use App\Service\QuizResultService;
use PHPUnit\Framework\TestCase;

class QuizResultServiceTest extends TestCase
{
    private QuizResultService $service;

    protected function setUp(): void
    {
        $this->service = new QuizResultService();
    }

    /**
     * Test: Calcul du scores total
     */
    public function testCalculateTotalScore(): void
    {
        // Arrange
        $quiz = $this->createTestQuiz(3); // 3 questions
        $answers = [
            1 => 2,
            2 => 3,
            3 => 1,
        ];

        // Act
        $result = $this->service->calculate($quiz, $answers);

        // Assert
        $this->assertEquals(6, $result['totalScore']);
        $this->assertIsArray($result['categoryScores']);
    }

    /**
     * Test: Calcul avec questions inverses
     */
    public function testCalculateWithReverseScoring(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz with Reverse');

        $q1 = new Question();
        $q1->setText('Question 1');
        $q1->setType('likert_0_4');
        $q1->setCategory('stress');
        $q1->setOrderInQuiz(1);
        $q1->setReverse(false);
        $q1->setQuiz($quiz);

        $q2 = new Question();
        $q2->setText('Question 2 (Reverse)');
        $q2->setType('likert_0_4');
        $q2->setCategory('stress');
        $q2->setOrderInQuiz(2);
        $q2->setReverse(true); // Question inversée
        $q2->setQuiz($quiz);

        $quiz->addQuestion($q1);
        $quiz->addQuestion($q2);

        $answers = [1 => 2, 2 => 3]; // q2 avec 3 devient 4-3=1

        // Act
        $result = $this->service->calculate($quiz, $answers);

        // Assert
        $this->assertEquals(3, $result['totalScore']); // 2 + (4-3)
        $this->assertArrayHasKey('stress', $result['categoryScores']);
        $this->assertEquals(3, $result['categoryScores']['stress']);
    }

    /**
     * Test: Recommandations filtrées par score
     */
    public function testRecommendationFilteringByScore(): void
    {
        // Arrange
        $quiz = $this->createTestQuiz(2);

        $reco1 = new Recommandation();
        $reco1->setName('Reco Low Score');
        $reco1->setTitle('For low scores');
        $reco1->setMinScore(0);
        $reco1->setMaxScore(5);
        $reco1->setSeverity('low');
        $reco1->setQuiz($quiz);

        $reco2 = new Recommandation();
        $reco2->setName('Reco High Score');
        $reco2->setTitle('For high scores');
        $reco2->setMinScore(6);
        $reco2->setMaxScore(10);
        $reco2->setSeverity('high');
        $reco2->setQuiz($quiz);

        $quiz->addRecommandation($reco1);
        $quiz->addRecommandation($reco2);

        // Test score 4: should only get reco1
        $result = $this->service->calculate($quiz, [1 => 2, 2 => 2]);
        $this->assertCount(1, $result['recommendations']);
        $this->assertEquals('Reco Low Score', $result['recommendations'][0]->getName());

        // Test score 7: should only get reco2
        $result = $this->service->calculate($quiz, [1 => 3, 2 => 4]);
        $this->assertCount(1, $result['recommendations']);
        $this->assertEquals('Reco High Score', $result['recommendations'][0]->getName());
    }

    /**
     * Test: Interprétation du score
     */
    public function testInterpretation(): void
    {
        // Test interpretation by examining result
        $quiz = $this->createTestQuiz(1);

        // Low score (< 15)
        $result = $this->service->calculate($quiz, [1 => 2]);
        $this->assertStringContainsString('faible', strtolower($result['interpretation']));

        // High score (> 24)
        $quiz = $this->createTestQuiz(10);
        $answers = array_fill(1, 10, 4); // All max answers
        $result = $this->service->calculate($quiz, $answers);
        $this->assertStringContainsString('eleve', strtolower($result['interpretation']));
    }

    /**
     * Test: Empty answers handling
     */
    public function testEmptyAnswers(): void
    {
        // Arrange
        $quiz = $this->createTestQuiz(2);
        $answers = []; // Empty answers

        // Act
        $result = $this->service->calculate($quiz, $answers);

        // Assert - Missing answers should be treated as 0
        $this->assertGreaterThanOrEqual(0, $result['totalScore']);
        $this->assertIsArray($result['categoryScores']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * Test: Category score calculation
     */
    public function testCategoryScoreCalculation(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Category Test');

        // Create 4 questions: 2 stress, 2 anxiety
        for ($i = 1; $i <= 2; $i++) {
            $q = new Question();
            $q->setText("Stress Question $i");
            $q->setType('likert_0_4');
            $q->setCategory('stress');
            $q->setOrderInQuiz($i);
            $q->setQuiz($quiz);
            $quiz->addQuestion($q);
        }

        for ($i = 3; $i <= 4; $i++) {
            $q = new Question();
            $q->setText("Anxiety Question " . ($i - 2));
            $q->setType('likert_0_4');
            $q->setCategory('anxiete');
            $q->setOrderInQuiz($i);
            $q->setQuiz($quiz);
            $quiz->addQuestion($q);
        }

        $answers = [1 => 2, 2 => 1, 3 => 3, 4 => 4];

        // Act
        $result = $this->service->calculate($quiz, $answers);

        // Assert
        $this->assertEquals(3, $result['categoryScores']['stress']); // 2 + 1
        $this->assertEquals(7, $result['categoryScores']['anxiete']); // 3 + 4
    }

    /**
     * Helper: Create a test quiz with n questions
     */
    private function createTestQuiz(int $questionCount): Quiz
    {
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        for ($i = 1; $i <= $questionCount; $i++) {
            $question = new Question();
            $question->setText("Question $i");
            $question->setType('likert_0_4');
            $question->setCategory('stress');
            $question->setOrderInQuiz($i);
            $question->setReverse(false);
            $question->setQuiz($quiz);
            $quiz->addQuestion($question);
        }

        return $quiz;
    }
}
