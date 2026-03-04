<?php

namespace App\Service;

use App\Entity\InscriptionEvenement;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;

class InscriptionEvenementManager
{
    public function __construct(private ?EntityManagerInterface $entityManager = null)
    {
    }

    public function validate(InscriptionEvenement $inscription): bool
    {
        
        if ($inscription->getEvenement() === null || $inscription->getUtilisateur() === null) {
            throw new InvalidArgumentException('L\'evenement et l\'utilisateur sont obligatoires.');
        }

    
        $dateInscription = $inscription->getDateInscription();
        if ($dateInscription === null) {
            throw new InvalidArgumentException('La date d\'inscription est obligatoire.');
        }

        $now = new \DateTime();
        if ($dateInscription > $now) {
            throw new InvalidArgumentException('La date d\'inscription ne peut pas etre dans le futur.');
        }

      
        $statutsValides = ['en_attente', 'confirmee', 'annulee'];
        $statut = (string) $inscription->getStatut();

        if (!in_array($statut, $statutsValides, true)) {
            throw new InvalidArgumentException('Le statut de l\'inscription est invalide.');
        }

        if ($inscription->isPresent() === true && $statut !== 'confirmee') {
            throw new InvalidArgumentException('Une inscription marquee presente doit etre confirmee.');
        }

        return true;
    }

    
    public function validateUniqueInscription(InscriptionEvenement $inscription, array $existingInscriptions): bool
    {
        $evenement = $inscription->getEvenement();
        $utilisateur = $inscription->getUtilisateur();

        if ($evenement === null || $utilisateur === null) {
            throw new InvalidArgumentException('L\'evenement et l\'utilisateur sont obligatoires.');
        }

        foreach ($existingInscriptions as $existing) {
            if (
                $existing->getEvenement() === $evenement &&
                $existing->getUtilisateur() === $utilisateur
            ) {
                throw new InvalidArgumentException('Utilisateur deja inscrit a cet evenement.');
            }
        }

        return true;
    }

    public function canMarkPresent(InscriptionEvenement $inscription): bool
    {
        return (string) $inscription->getStatut() === 'confirmee';
    }

    public function canCancel(InscriptionEvenement $inscription, ?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();
        $evenement = $inscription->getEvenement();

        if ($evenement === null) {
            return false;
        }

        $deadline = $evenement->getDateDebut()->sub(new \DateInterval('PT24H'));

        return $now < $deadline;
    }

    public function create(InscriptionEvenement $inscription): InscriptionEvenement
    {
        $this->validate($inscription);
        $em = $this->requireEntityManager();
        $em->persist($inscription);
        $em->flush();

        return $inscription;
    }

    public function findById(int $id): ?InscriptionEvenement
    {
        $result = $this->requireEntityManager()
            ->getRepository(InscriptionEvenement::class)
            ->find($id);

        return $result instanceof InscriptionEvenement ? $result : null;
    }

    public function update(InscriptionEvenement $inscription): InscriptionEvenement
    {
        $this->validate($inscription);
        $this->requireEntityManager()->flush();

        return $inscription;
    }

    public function delete(InscriptionEvenement $inscription): void
    {
        $em = $this->requireEntityManager();
        $em->remove($inscription);
        $em->flush();
    }

    private function requireEntityManager(): EntityManagerInterface
    {
        if ($this->entityManager === null) {
            throw new LogicException('EntityManager indisponible pour operation CRUD.');
        }

        return $this->entityManager;
    }
}
