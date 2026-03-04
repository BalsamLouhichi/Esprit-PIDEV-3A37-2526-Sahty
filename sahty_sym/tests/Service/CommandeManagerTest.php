<?php

namespace App\Tests\Service;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\CommandeManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CommandeManagerTest extends TestCase
{
    public function testCreatePersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commande = new Commande();

        $em->expects($this->once())->method('persist')->with($commande);
        $em->expects($this->once())->method('flush');

        $result = $manager->create($commande);

        $this->assertSame($commande, $result);
    }

    public function testUpdateFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commande = new Commande();

        $em->expects($this->once())->method('flush');

        $result = $manager->update($commande);

        $this->assertSame($commande, $result);
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commande = new Commande();

        $em->expects($this->once())->method('remove')->with($commande);
        $em->expects($this->once())->method('flush');

        $manager->delete($commande);

        $this->assertTrue(true);
    }

    public function testFindDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commande = new Commande();

        $repo->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($commande);

        $this->assertSame($commande, $manager->find(10));
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $repo->expects($this->once())
            ->method('find')
            ->with(9999)
            ->willReturn(null);

        $this->assertNull($manager->find(9999));
    }

    public function testFindAllDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commandes = [new Commande(), new Commande()];

        $repo->expects($this->once())
            ->method('findAll')
            ->willReturn($commandes);

        $this->assertSame($commandes, $manager->findAll());
    }

    public function testFindByStatutDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commandes = [new Commande()];

        $repo->expects($this->once())
            ->method('findByStatut')
            ->with('en_attente')
            ->willReturn($commandes);

        $this->assertSame($commandes, $manager->findByStatut('en_attente'));
    }

    public function testFindByStatutReturnsEmptyArray(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $repo->expects($this->once())
            ->method('findByStatut')
            ->with('inconnu')
            ->willReturn([]);

        $this->assertSame([], $manager->findByStatut('inconnu'));
    }

    public function testFindByStatutWithEmptyStringDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $repo->expects($this->once())
            ->method('findByStatut')
            ->with('')
            ->willReturn([]);

        $this->assertSame([], $manager->findByStatut(''));
    }

    public function testFindByParapharmacieDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $commandes = [new Commande()];

        $repo->expects($this->once())
            ->method('findByParapharmacie')
            ->with(3)
            ->willReturn($commandes);

        $this->assertSame($commandes, $manager->findByParapharmacie(3));
    }

    public function testFindByParapharmacieReturnsEmptyArray(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $repo->expects($this->once())
            ->method('findByParapharmacie')
            ->with(404)
            ->willReturn([]);

        $this->assertSame([], $manager->findByParapharmacie(404));
    }

    public function testFindByParapharmacieWithZeroDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $repo->expects($this->once())
            ->method('findByParapharmacie')
            ->with(0)
            ->willReturn([]);

        $this->assertSame([], $manager->findByParapharmacie(0));
    }

    public function testFindByParapharmacieThrowsTypeErrorForNull(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $this->expectException(\TypeError::class);
        /** @var mixed $invalidParapharmacieId */
        $invalidParapharmacieId = null;
        $manager->findByParapharmacie($invalidParapharmacieId);
    }

    public function testFindByStatutThrowsTypeErrorForNull(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(CommandeRepository::class);
        $manager = new CommandeManager($em, $repo);

        $this->expectException(\TypeError::class);
        /** @var mixed $invalidStatut */
        $invalidStatut = null;
        $manager->findByStatut($invalidStatut);
    }
}
