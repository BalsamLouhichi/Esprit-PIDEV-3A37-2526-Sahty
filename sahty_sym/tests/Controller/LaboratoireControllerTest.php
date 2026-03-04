<?php

namespace App\Tests\Controller;

use App\Entity\Laboratoire;
use App\Entity\Patient;
use App\Entity\ResponsableLaboratoire;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class LaboratoireControllerTest extends WebTestCase
{
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

        $dbPath = dirname(__DIR__, 2) . '/var/test_labo_controller.sqlite';
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

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testLaboratoireCrudFlow(): void
    {
        $responsable = $this->createResponsableLaboratoire();

        $createClient = $this->createAuthenticatedClient($responsable);
        $crawler = $createClient->request('GET', '/laboratoire/new');
        $this->assertResponseIsSuccessful();

        $createClient->submitForm('Enregistrer le laboratoire', [
            'laboratoire[nom]' => 'Labo CRUD',
            'laboratoire[ville]' => 'Tunis',
            'laboratoire[adresse]' => '10 Rue du Test',
            'laboratoire[telephone]' => '+216 11223344',
            'laboratoire[latitude]' => '36.80',
            'laboratoire[longitude]' => '10.18',
            'laboratoire[description]' => 'Laboratoire cree par test CRUD',
            'laboratoire[disponible]' => '1',
        ]);
        $this->assertResponseRedirects();

        $laboratoire = $this->em->getRepository(Laboratoire::class)->findOneBy(['nom' => 'Labo CRUD']);
        $this->assertInstanceOf(Laboratoire::class, $laboratoire);
        $this->assertSame($responsable->getId(), $laboratoire->getResponsable()?->getId());

        $patient = $this->createPatient();
        $patientClient = $this->createAuthenticatedClient($patient);

        $patientClient->request('GET', '/laboratoire/' . $laboratoire->getId());
        $this->assertResponseIsSuccessful();

        $patientClient->request('GET', '/laboratoire/' . $laboratoire->getId() . '/edit');
        $this->assertResponseIsSuccessful();

        $patientClient->submitForm('Enregistrer les modifications', [
            'laboratoire[nom]' => 'Labo CRUD Updated',
            'laboratoire[ville]' => 'Sfax',
            'laboratoire[adresse]' => '20 Avenue Update',
            'laboratoire[telephone]' => '99887766',
            'laboratoire[latitude]' => '34.74',
            'laboratoire[longitude]' => '10.76',
            'laboratoire[description]' => 'Mise a jour CRUD',
        ]);
        $this->assertResponseRedirects('/laboratoire/' . $laboratoire->getId());

        $this->em->clear();
        $updated = $this->em->getRepository(Laboratoire::class)->find($laboratoire->getId());
        $this->assertInstanceOf(Laboratoire::class, $updated);
        $this->assertSame('Labo CRUD Updated', $updated->getNom());
        $this->assertSame('Sfax', $updated->getVille());

        /** @var CsrfTokenManagerInterface $csrf */
        $csrf = static::getContainer()->get('security.csrf.token_manager');
        $session = $patientClient->getRequest()?->getSession();
        $this->assertNotNull($session);
        /** @var RequestStack $requestStack */
        $requestStack = static::getContainer()->get(RequestStack::class);
        $csrfRequest = Request::create('/');
        $csrfRequest->setSession($session);
        $requestStack->push($csrfRequest);
        $tokenValue = $csrf->getToken('delete_labo_' . $updated->getId())->getValue();
        $requestStack->pop();
        $session->save();

        $patientClient->request('POST', '/laboratoire/' . $updated->getId() . '/delete', [
            '_token' => $tokenValue,
        ]);
        $this->assertResponseRedirects('/laboratoire/');

        $this->em->clear();
        $deleted = $this->em->getRepository(Laboratoire::class)->find($updated->getId());
        $this->assertNull($deleted);
    }

    private function createPatient(): Patient
    {
        $user = new Patient();
        $user->setEmail(sprintf('patient-labo-%s@example.test', uniqid('', true)));
        $user->setNom('Patient');
        $user->setPrenom('Tester');
        $user->setPassword('hashed-password');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createResponsableLaboratoire(): ResponsableLaboratoire
    {
        $user = new ResponsableLaboratoire();
        $user->setEmail(sprintf('resp-labo-%s@example.test', uniqid('', true)));
        $user->setNom('Responsable');
        $user->setPrenom('Tester');
        $user->setPassword('hashed-password');

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createAuthenticatedClient(object $user): KernelBrowser
    {
        static::ensureKernelShutdown();
        $client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $client->loginUser($user, 'main');

        return $client;
    }

    private static function setEnv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    protected function tearDown(): void
    {
        $this->em->close();
        unset($this->em);
        static::ensureKernelShutdown();
        parent::tearDown();
    }
}
