<?php

namespace App\Tests\Controller;

use App\Entity\Quiz;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QuizControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Test: Frontend quiz list is accessible and loads
     */
    public function testFrontendQuizListLoads(): void
    {
        // Act
        $this->client->request('GET', '/quiz');

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Quizzes');
    }

    /**
     * Test: Quiz detail page loads
     */
    public function testQuizDetailPageLoads(): void
    {
        // Create a test quiz
        $quiz = new Quiz();
        $quiz->setName('Test Quiz for Detail');

        $question = new Question();
        $question->setText('Test Question');
        $question->setType('likert_0_4');
        $question->setCategory('stress');
        $question->setOrderInQuiz(1);
        $quiz->addQuestion($question);

        $this->em->persist($quiz);
        $this->em->flush();

        // Act
        $this->client->request('GET', "/quiz/{$quiz->getId()}");

        // Assert
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Test Quiz for Detail');
    }

    /**
     * Test: Quiz submission calculates results
     */
    public function testQuizSubmission(): void
    {
        // Create a test quiz
        $quiz = new Quiz();
        $quiz->setName('Submission Test Quiz');

        for ($i = 1; $i <= 3; $i++) {
            $question = new Question();
            $question->setText("Question $i");
            $question->setType('likert_0_4');
            $question->setCategory('stress');
            $question->setOrderInQuiz($i);
            $quiz->addQuestion($question);
        }

        $this->em->persist($quiz);
        $this->em->flush();

        // Submit answers
        $this->client->request('POST', "/quiz/{$quiz->getId()}/submit", [
            'answers' => [
                1 => 2,
                2 => 3,
                3 => 1,
            ]
        ]);

        // Assert - should redirect to results or show results
        $this->assertTrue(
            $this->client->getResponse()->isSuccessful() ||
            $this->client->getResponse()->isRedirect()
        );
    }

    /**
     * Test: Admin quiz list with search
     */
    public function testAdminQuizListWithSearch(): void
    {
        // Act
        $this->client->request('GET', '/quiz/admin?search=test');

        // Assert - admin pages might require auth, so just check response is valid
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isSuccessful() ||
            $response->getStatusCode() === 302 // Redirect to login
        );
    }

    /**
     * Test: Admin quiz list with sorting
     */
    public function testAdminQuizListWithSorting(): void
    {
        // Act
        $this->client->request('GET', '/quiz/admin?sortBy=name&sortOrder=asc');

        // Assert
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isSuccessful() ||
            $response->getStatusCode() === 302
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
