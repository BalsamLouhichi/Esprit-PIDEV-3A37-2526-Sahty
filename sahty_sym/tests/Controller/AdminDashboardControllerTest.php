<?php

namespace App\Tests\Controller;

use App\Entity\Administrateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminDashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAdminDashboardRequiresAuthentication(): void
    {
        $url = static::getContainer()->get(RouterInterface::class)->generate('admin');
        $this->client->request('GET', $url);

        $this->assertResponseRedirects('/login');
    }

    public function testAdminDashboardLoadsForAdmin(): void
    {
        $admin = new Administrateur();
        $admin->setEmail(sprintf('admin-%s@example.test', uniqid('', true)));
        $admin->setNom('Admin');
        $admin->setPrenom('Test');
        $admin->setPassword('hashed-password');

        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin, 'main');
        $url = static::getContainer()->get(RouterInterface::class)->generate('admin');
        $this->client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertPageTitleContains('Admin Dashboard');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
