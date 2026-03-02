<?php

namespace App\Tests\Service;

use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\Recommandation;
use App\Service\QuizResultService;
use PHPUnit\Framework\TestCase;

class QuizResultServiceTest extends TestCase
{
    public function testCalculateBuildsScoreProblemsAndRecommendations(): void
    {
        $quiz = new Quiz();
        $quiz->setName('Quiz test');

        $q1 = (new Question())
            ->setText('Q1')
            ->setCategory('stress')
            ->setOrderInQuiz(1)
            ->setReverse(false);
        $q2 = (new Question())
            ->setText('Q2')
            ->setCategory('stress')
            ->setOrderInQuiz(2)
            ->setReverse(false);
        $q3 = (new Question())
            ->setText('Q3')
            ->setCategory('stress')
            ->setOrderInQuiz(3)
            ->setReverse(true);

        $quiz->addQuestion($q1)->addQuestion($q2)->addQuestion($q3);

        $high = (new Recommandation())
            ->setQuiz($quiz)
            ->setName('High')
            ->setTitle('High title')
            ->setMinScore(0)
            ->setMaxScore(20)
            ->setTargetCategories('stress')
            ->setSeverity('high');
        $low = (new Recommandation())
            ->setQuiz($quiz)
            ->setName('Low')
            ->setTitle('Low title')
            ->setMinScore(0)
            ->setMaxScore(20)
            ->setTargetCategories('stress')
            ->setSeverity('low');

        $quiz->addRecommandation($low)->addRecommandation($high);

        $service = new QuizResultService();
        $result = $service->calculate($quiz, [
            1 => 4,
            2 => 4,
            3 => 0,
        ]);

        self::assertSame(12, $result['totalScore']);
        self::assertSame(12, $result['maxScore']);
        self::assertSame(['stress' => 12], $result['categoryScores']);
        self::assertSame(['stress'], $result['problems']);
        self::assertCount(2, $result['recommendations']);
        self::assertSame('high', $result['recommendations'][0]->getSeverity());
        self::assertSame('low', $result['recommendations'][1]->getSeverity());
        self::assertStringContainsString('faible', mb_strtolower((string) $result['interpretation']));
    }
}

