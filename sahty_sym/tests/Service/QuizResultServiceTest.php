<?php

namespace App\Tests\Service;

use App\Entity\Question;
use App\Entity\Quiz;
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

    public function testCalculateTotalScore(): void
    {
        $quiz = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
            ['id' => 2, 'category' => 'stress', 'reverse' => false],
            ['id' => 3, 'category' => 'stress', 'reverse' => false],
        ]);

        $result = $this->service->calculate($quiz, [1 => 2, 2 => 3, 3 => 1]);

        $this->assertSame(6, $result['totalScore']);
        $this->assertSame(12, $result['maxScore']);
        $this->assertSame(6, $result['categoryScores']['stress']);
    }

    public function testCalculateWithReverseScoring(): void
    {
        $quiz = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
            ['id' => 2, 'category' => 'stress', 'reverse' => true],
        ]);

        $result = $this->service->calculate($quiz, [1 => 2, 2 => 3]);

        $this->assertSame(3, $result['totalScore']);
        $this->assertSame(3, $result['categoryScores']['stress']);
    }

    public function testRecommendationFilteringByScore(): void
    {
        $quiz = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
            ['id' => 2, 'category' => 'stress', 'reverse' => false],
        ]);

        $low = new Recommandation();
        $low->setName('Reco Low Score');
        $low->setTitle('For low scores');
        $low->setMinScore(0);
        $low->setMaxScore(5);
        $low->setSeverity('low');

        $high = new Recommandation();
        $high->setName('Reco High Score');
        $high->setTitle('For high scores');
        $high->setMinScore(6);
        $high->setMaxScore(10);
        $high->setSeverity('high');

        $quiz->addRecommandation($low);
        $quiz->addRecommandation($high);

        $resultLow = $this->service->calculate($quiz, [1 => 2, 2 => 2]);
        $this->assertCount(1, $resultLow['recommendations']);
        $this->assertSame('Reco Low Score', $resultLow['recommendations'][0]->getName());

        $resultHigh = $this->service->calculate($quiz, [1 => 3, 2 => 4]);
        $this->assertCount(1, $resultHigh['recommendations']);
        $this->assertSame('Reco High Score', $resultHigh['recommendations'][0]->getName());
    }

    public function testInterpretationRanges(): void
    {
        $quizLow = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
        ]);

        $lowResult = $this->service->calculate($quizLow, [1 => 2]);
        $this->assertStringContainsString('faible', strtolower($lowResult['interpretation']));

        $quizHigh = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
            ['id' => 2, 'category' => 'stress', 'reverse' => false],
            ['id' => 3, 'category' => 'stress', 'reverse' => false],
            ['id' => 4, 'category' => 'stress', 'reverse' => false],
            ['id' => 5, 'category' => 'stress', 'reverse' => false],
            ['id' => 6, 'category' => 'stress', 'reverse' => false],
            ['id' => 7, 'category' => 'stress', 'reverse' => false],
        ]);

        $highResult = $this->service->calculate($quizHigh, [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 4, 7 => 4]);
        $this->assertStringContainsString('consulter', strtolower($highResult['interpretation']));
    }

    public function testCategoryScoreCalculationAndProblemThreshold(): void
    {
        $quiz = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
            ['id' => 2, 'category' => 'stress', 'reverse' => false],
            ['id' => 5, 'category' => 'stress', 'reverse' => false],
            ['id' => 3, 'category' => 'anxiete', 'reverse' => false],
            ['id' => 4, 'category' => 'anxiete', 'reverse' => false],
        ]);

        $result = $this->service->calculate($quiz, [1 => 2, 2 => 1, 5 => 0, 3 => 3, 4 => 4]);

        $this->assertSame(3, $result['categoryScores']['stress']);
        $this->assertSame(7, $result['categoryScores']['anxiete']);
        $this->assertSame([], $result['problems']);

        $resultHighStress = $this->service->calculate($quiz, [1 => 4, 2 => 4, 5 => 2, 3 => 0, 4 => 0]);
        $this->assertContains('stress', $resultHighStress['problems']);
    }

    public function testEmptyAnswersAreHandledAsZero(): void
    {
        $quiz = $this->createQuizWithQuestions([
            ['id' => 1, 'category' => 'stress', 'reverse' => false],
            ['id' => 2, 'category' => 'anxiete', 'reverse' => false],
        ]);

        $result = $this->service->calculate($quiz, []);

        $this->assertSame(0, $result['totalScore']);
        $this->assertSame(0, $result['categoryScores']['stress']);
        $this->assertSame(0, $result['categoryScores']['anxiete']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * @param array<int, array{id:int, category:string, reverse:bool}> $questionSpecs
     */
    private function createQuizWithQuestions(array $questionSpecs): Quiz
    {
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        $order = 1;
        foreach ($questionSpecs as $spec) {
            $question = new Question();
            $question->setText('Question ' . $spec['id']);
            $question->setType('likert_0_4');
            $question->setCategory($spec['category']);
            $question->setOrderInQuiz($order++);
            $question->setReverse($spec['reverse']);
            $question->setQuiz($quiz);

            $this->setEntityId($question, $spec['id']);
            $quiz->addQuestion($question);
        }

        return $quiz;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
