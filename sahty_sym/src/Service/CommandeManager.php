<?php

namespace App\Service;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

class CommandeManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommandeRepository $commandeRepository
    ) {
    }

    public function create(Commande $commande): Commande
    {
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        return $commande;
    }

    public function update(Commande $commande): Commande
    {
        $this->entityManager->flush();

        return $commande;
    }

    public function delete(Commande $commande): void
    {
        $this->entityManager->remove($commande);
        $this->entityManager->flush();
    }

    public function find(int $id): ?Commande
    {
        return $this->commandeRepository->find($id);
    }

    /**
     * @return Commande[]
     */
    public function findAll(): array
    {
        return $this->commandeRepository->findAll();
    }

    /**
     * @return Commande[]
     */
    public function findByStatut(string $statut): array
    {
        return $this->commandeRepository->findByStatut($statut);
    }

    /**
     * @return Commande[]
     */
    public function findByParapharmacie(int $parapharmacieId): array
    {
        return $this->commandeRepository->findByParapharmacie($parapharmacieId);
    }
}
