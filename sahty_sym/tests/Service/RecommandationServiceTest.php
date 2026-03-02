<?php

namespace App\Tests\Service;

use App\Entity\Quiz;
use App\Entity\Recommandation;
use App\Service\RecommandationService;
use PHPUnit\Framework\TestCase;

class RecommandationServiceTest extends TestCase
{
    public function testGetFilteredByScoreAndDetectedCategory(): void
    {
        $quiz = new Quiz();
        $quiz->setName('Quiz test');

        $stressHigh = (new Recommandation())
            ->setQuiz($quiz)
            ->setName('Stress high')
            ->setTitle('Stress high')
            ->setMinScore(10)
            ->setMaxScore(30)
            ->setTargetCategories('stress')
            ->setSeverity('high');

        $sleepLow = (new Recommandation())
            ->setQuiz($quiz)
            ->setName('Sleep low')
            ->setTitle('Sleep low')
            ->setMinScore(0)
            ->setMaxScore(30)
            ->setTargetCategories('sommeil')
            ->setSeverity('low');

        $quiz->addRecommandation($sleepLow)->addRecommandation($stressHigh);

        $service = new RecommandationService();
        $result = $service->getFiltered($quiz, 18, ['Stress']);

        self::assertCount(1, $result);
        self::assertSame('Stress high', $result[0]->getTitle());
    }

    public function testResolveVideoUrlFallsBackByCategory(): void
    {
        $reco = (new Recommandation())
            ->setName('Reco')
            ->setTitle('Reco')
            ->setMinScore(0)
            ->setMaxScore(10)
            ->setTargetCategories('stress')
            ->setSeverity('medium');

        $service = new RecommandationService();
        $url = $service->resolveVideoUrl($reco);

        self::assertSame('https://www.youtube.com/embed/hnpQrMqDoqE', $url);
    }
}

