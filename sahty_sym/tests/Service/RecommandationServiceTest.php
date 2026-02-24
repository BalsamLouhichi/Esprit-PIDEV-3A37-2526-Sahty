<?php

namespace App\Tests\Service;

use App\Entity\Quiz;
use App\Entity\Recommandation;
use App\Repository\RecommandationRepository;
use App\Service\RecommandationService;
use PHPUnit\Framework\TestCase;

class RecommandationServiceTest extends TestCase
{
    private RecommandationService $service;
    private RecommandationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RecommandationRepository::class);
        $this->service = new RecommandationService($this->repository);
    }

    /**
     * Test: Filter recommendations by score
     */
    public function testFilterByScore(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        $reco1 = new Recommandation();
        $reco1->setName('Low Score Reco');
        $reco1->setMinScore(0);
        $reco1->setMaxScore(5);
        $reco1->setSeverity('low');

        $reco2 = new Recommandation();
        $reco2->setName('High Score Reco');
        $reco2->setMinScore(10);
        $reco2->setMaxScore(20);
        $reco2->setSeverity('high');

        $quiz->addRecommandation($reco1);
        $quiz->addRecommandation($reco2);

        // Act - score 3 should match reco1
        $result = $this->service->getFiltered($quiz, 3);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Low Score Reco', $result[0]->getName());

        // Act - score 15 should match reco2
        $result = $this->service->getFiltered($quiz, 15);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('High Score Reco', $result[0]->getName());
    }

    /**
     * Test: Filter recommendations by categories
     */
    public function testFilterByCategories(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        $reco1 = new Recommandation();
        $reco1->setName('Stress Reco');
        $reco1->setTargetCategories('stress');
        $reco1->setSeverity('medium');

        $reco2 = new Recommandation();
        $reco2->setName('Anxiety Reco');
        $reco2->setTargetCategories('anxiete');
        $reco2->setSeverity('high');

        $quiz->addRecommandation($reco1);
        $quiz->addRecommandation($reco2);

        // Act - only problems = ['stress']
        $result = $this->service->getFiltered($quiz, 10, ['stress']);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Stress Reco', $result[0]->getName());

        // Act - problems = ['stress', 'anxiete']
        $result = $this->service->getFiltered($quiz, 10, ['stress', 'anxiete']);

        // Assert
        $this->assertCount(2, $result);
    }

    /**
     * Test: Recommendations sorted by severity
     */
    public function testSortBySeverity(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        $reco1 = new Recommandation();
        $reco1->setName('Low Severity');
        $reco1->setSeverity('low');

        $reco2 = new Recommandation();
        $reco2->setName('High Severity');
        $reco2->setSeverity('high');

        $reco3 = new Recommandation();
        $reco3->setName('Medium Severity');
        $reco3->setSeverity('medium');

        $quiz->addRecommandation($reco1);
        $quiz->addRecommandation($reco2);
        $quiz->addRecommandation($reco3);

        // Act
        $result = $this->service->getFiltered($quiz, 10);

        // Assert - should be sorted: high > medium > low
        $this->assertCount(3, $result);
        $this->assertEquals('High Severity', $result[0]->getName());
        $this->assertEquals('Medium Severity', $result[1]->getName());
        $this->assertEquals('Low Severity', $result[2]->getName());
    }

    /**
     * Test: Group recommendations by severity
     */
    public function testGroupBySeverity(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        for ($i = 0; $i < 2; $i++) {
            $reco = new Recommandation();
            $reco->setName("High $i");
            $reco->setSeverity('high');
            $quiz->addRecommandation($reco);
        }

        for ($i = 0; $i < 3; $i++) {
            $reco = new Recommandation();
            $reco->setName("Medium $i");
            $reco->setSeverity('medium');
            $quiz->addRecommandation($reco);
        }

        // Act
        $grouped = $this->service->getGroupedBySeverity($quiz);

        // Assert
        $this->assertCount(2, $grouped['high']);
        $this->assertCount(3, $grouped['medium']);
        $this->assertCount(0, $grouped['low']);
    }

    /**
     * Test: Count recommendations by severity
     */
    public function testCountBySeverity(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        for ($i = 0; $i < 2; $i++) {
            $reco = new Recommandation();
            $reco->setSeverity('high');
            $quiz->addRecommandation($reco);
        }

        for ($i = 0; $i < 1; $i++) {
            $reco = new Recommandation();
            $reco->setSeverity('medium');
            $quiz->addRecommandation($reco);
        }

        // Act
        $count = $this->service->countBySeverity($quiz);

        // Assert
        $this->assertEquals(2, $count['high']);
        $this->assertEquals(1, $count['medium']);
        $this->assertEquals(0, $count['low']);
    }

    /**
     * Test: Get urgent recommendations
     */
    public function testGetUrgent(): void
    {
        // Arrange
        $quiz = new Quiz();
        $quiz->setName('Test Quiz');

        // Create mix of recommendations
        $recoLow = new Recommandation();
        $recoLow->setName('Low');
        $recoLow->setSeverity('low');
        $quiz->addRecommandation($recoLow);

        $recoHigh = new Recommandation();
        $recoHigh->setName('High');
        $recoHigh->setSeverity('high');
        $quiz->addRecommandation($recoHigh);

        // Act
        $urgent = $this->service->getUrgent($quiz);

        // Assert
        $this->assertCount(1, $urgent);
        $this->assertEquals('High', reset($urgent)->getName());
    }

    /**
     * Test: Resolve category fallback video when no manual URL is provided
     */
    public function testResolveVideoUrlFromCategory(): void
    {
        $reco = new Recommandation();
        $reco->setTypeProbleme('Stress chronique');

        $video = $this->service->resolveVideoUrl($reco);

        $this->assertEquals('https://www.youtube.com/embed/hnpQrMqDoqE', $video);
    }

    /**
     * Test: Convert YouTube watch URL to embed URL
     */
    public function testResolveManualYoutubeUrlNormalization(): void
    {
        $reco = new Recommandation();
        $reco->setVideoUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        $video = $this->service->resolveVideoUrl($reco);

        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $video);
    }

    /**
     * Test: Resolve bien_etre category mapping
     */
    public function testResolveVideoUrlFromBienEtreCategory(): void
    {
        $reco = new Recommandation();
        $reco->setTypeProbleme('bien_etre');

        $video = $this->service->resolveVideoUrl($reco);

        $this->assertNotNull($video);
    }

    /**
     * Test: Unknown category still returns default video
     */
    public function testResolveVideoUrlDefaultFallback(): void
    {
        $reco = new Recommandation();
        $reco->setTypeProbleme('categorie_inconnue');

        $video = $this->service->resolveVideoUrl($reco);

        $this->assertEquals('https://www.youtube.com/embed/hnpQrMqDoqE', $video);
    }
}
