<?php
// src/Service/ProduitManager.php

namespace App\Service;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProduitManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProduitRepository $produitRepository
    ) {
    }

    public function create(Produit $produit): Produit
    {
        $this->entityManager->persist($produit);
        $this->entityManager->flush();

        return $produit;
    }

    public function update(Produit $produit): Produit
    {
        $this->entityManager->flush();

        return $produit;
    }

    public function delete(Produit $produit): void
    {
        $this->entityManager->remove($produit);
        $this->entityManager->flush();
    }

    public function find(int $id): ?Produit
    {
        return $this->produitRepository->find($id);
    }

    /**
     * @return Produit[]
     */
    public function findAll(): array
    {
        return $this->produitRepository->findAll();
    }

    /**
     * @return Produit[]
     */
    public function search(string $term): array
    {
        return $this->produitRepository->search($term);
    }

    /**
     * @return Produit[]
     */
    public function findByCategorie(string $categorie): array
    {
        return $this->produitRepository->findByCategorie($categorie);
    }

    /**
     * @return Produit[]
     */
    public function findPromotions(): array
    {
        return $this->produitRepository->findPromotions();
    }
}
