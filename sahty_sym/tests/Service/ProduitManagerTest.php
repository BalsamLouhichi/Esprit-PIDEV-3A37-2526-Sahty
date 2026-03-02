<?php
// tests/Service/ProduitManagerTest.php

namespace App\Tests\Service;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use App\Service\ProduitManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ProduitManagerTest extends TestCase
{
    public function testCreatePersistsAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ProduitRepository::class);
        $manager = new ProduitManager($em, $repo);

        $produit = new Produit();

        $em->expects($this->once())->method('persist')->with($produit);
        $em->expects($this->once())->method('flush');

        $result = $manager->create($produit);

        $this->assertSame($produit, $result);
    }

    public function testUpdateFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ProduitRepository::class);
        $manager = new ProduitManager($em, $repo);

        $produit = new Produit();

        $em->expects($this->once())->method('flush');

        $result = $manager->update($produit);

        $this->assertSame($produit, $result);
    }

    public function testDeleteRemovesAndFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ProduitRepository::class);
        $manager = new ProduitManager($em, $repo);

        $produit = new Produit();

        $em->expects($this->once())->method('remove')->with($produit);
        $em->expects($this->once())->method('flush');

        $manager->delete($produit);

        $this->assertTrue(true);
    }

    public function testFindDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ProduitRepository::class);
        $manager = new ProduitManager($em, $repo);

        $produit = new Produit();

        $repo->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($produit);

        $this->assertSame($produit, $manager->find(10));
    }

    public function testFindAllDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ProduitRepository::class);
        $manager = new ProduitManager($em, $repo);

        $produits = [new Produit(), new Produit()];

        $repo->expects($this->once())
            ->method('findAll')
            ->willReturn($produits);

        $this->assertSame($produits, $manager->findAll());
    }

    public function testSearchDelegatesToRepository(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(ProduitRepository::class);
        $manager = new ProduitManager($em, $repo);

        $produits = [new Produit()];

        $repo->expects($this->once())
            ->method('search')
            ->with('doliprane')
            ->willReturn($produits);

        $this->assertSame($produits, $manager->search('doliprane'));
    }
}
