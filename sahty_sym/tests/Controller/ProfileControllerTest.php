<?php

namespace App\Tests\Controller;

use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testProfileRequiresAuthentication(): void
    {
        $this->client->request('GET', '/profile');

        $this->assertResponseRedirects('/login');
    }

    public function testProfileLoadsWhenAuthenticated(): void
    {
        $user = new Patient();
        $email = sprintf('profile-%s@example.test', uniqid('', true));
        $user->setEmail($email);
        $user->setNom('Doe');
        $user->setPrenom('Jane');
        $user->setPassword('hashed-password');

        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user, 'main');

        $this->client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.profile-email', $email);
        $this->assertSelectorTextContains('.profile-name', 'Jane Doe');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
