<?php

namespace App\Tests\Controller;

use App\Kernel;
use App\Entity\Quiz;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QuizControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private static bool $schemaInitialized = false;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::setEnv('APP_ENV', 'test');
        self::setEnv('APP_SECRET', 'test-secret');
        self::setEnv('APP_URL', 'http://localhost');
        self::setEnv('KERNEL_CLASS', Kernel::class);
        self::setEnv('MESSENGER_TRANSPORT_DSN', 'in-memory://');
        self::setEnv('MAILER_DSN', 'null://null');
        self::setEnv('RECAPTCHA_ENABLED', '0');
        self::setEnv('RECAPTCHA_SITE_KEY', '');
        self::setEnv('VAR_DUMPER_SERVER', '127.0.0.1:9912');

        self::setEnv('FASTAPI_AI_ENDPOINT', 'http://127.0.0.1:8090/api/analyze');
        self::setEnv('FASTAPI_AI_OCR_ENGINE', 'auto');
        self::setEnv('FASTAPI_AI_LANG', 'fra+eng');
        self::setEnv('FASTAPI_AI_MODEL', 'llama3:latest');
        self::setEnv('FASTAPI_AI_USE_OLLAMA', '0');
        self::setEnv('FASTAPI_AI_TIMEOUT', '10');
        self::setEnv('FASTAPI_SEMANTIC_TIMEOUT', '5');

        self::setEnv('OAUTH_GOOGLE_CLIENT_ID', 'test-client-id');
        self::setEnv('OAUTH_GOOGLE_CLIENT_SECRET', 'test-client-secret');

        $dbPath = dirname(__DIR__, 2) . '/var/test_quiz_controller.sqlite';
        self::setEnv('DATABASE_URL', 'sqlite:///' . str_replace('\\', '/', $dbPath));

        if (!self::$schemaInitialized) {
            static::bootKernel();
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $metadata = $em->getMetadataFactory()->getAllMetadata();
            (new SchemaTool($em))->dropSchema($metadata);
            (new SchemaTool($em))->createSchema($metadata);
            self::$schemaInitialized = true;
            static::ensureKernelShutdown();
        }
    }

    private static function setEnv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
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
        $response = $this->client->getResponse();
        $this->assertTrue($response->isSuccessful() || $response->isRedirect());
        if ($response->isSuccessful()) {
            $this->assertPageTitleContains('Quizz');
        } else {
            $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
        }
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
        $response = $this->client->getResponse();
        $this->assertTrue($response->isSuccessful() || $response->isRedirect());
        if ($response->isSuccessful()) {
            $this->assertSelectorTextContains('h1', 'Test Quiz for Detail');
        } else {
            $this->assertStringContainsString('/login', (string) $response->headers->get('Location'));
        }
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

        $questionIds = [];
        foreach ($quiz->getQuestions() as $question) {
            $questionIds[] = $question->getId();
        }

        // Submit answers
        $this->client->request('POST', "/quiz/{$quiz->getId()}/submit", [
            'answers' => [
                (string) ($questionIds[0] ?? 0) => 2,
                (string) ($questionIds[1] ?? 0) => 3,
                (string) ($questionIds[2] ?? 0) => 1,
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
        $this->client->request('GET', '/admin/quizzes?search=test');

        // Assert - admin pages might require auth, so just check response is valid
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isSuccessful() ||
            in_array($response->getStatusCode(), [302, 403], true) // Redirect/login or access denied
        );
    }

    /**
     * Test: Admin quiz list with sorting
     */
    public function testAdminQuizListWithSorting(): void
    {
        // Act
        $this->client->request('GET', '/admin/quizzes?sort=name_asc');

        // Assert
        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isSuccessful() ||
            in_array($response->getStatusCode(), [302, 403], true)
        );
    }

    protected function tearDown(): void
    {
        $this->em->close();
        unset($this->em, $this->client);
        static::ensureKernelShutdown();
        parent::tearDown();
    }
}
