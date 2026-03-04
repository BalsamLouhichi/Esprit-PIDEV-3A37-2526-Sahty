<?php
// src/Service/EvenementManager.php

namespace App\Service;

use App\Entity\Evenement;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;

class EvenementManager
{
    public function __construct(private ?EntityManagerInterface $entityManager = null)
    {
    }

    public function validate(Evenement $evenement): bool
    {
        $this->validateDates($evenement);
        $this->validatePlacesMax($evenement);
        $this->validateTarif($evenement);
        $this->validateTitre($evenement);

        return true;
    }

    public function isOpenForSubscription(Evenement $evenement, ?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();
        $statut = (string) $evenement->getStatut();

        if (!in_array($statut, ['planifie', 'confirme'], true)) {
            return false;
        }

        return $evenement->getDateDebut() > $now;
    }

    public function calculateRemainingPlaces(Evenement $evenement, int $currentInscriptions): ?int
    {
        $placesMax = $evenement->getPlacesMax();

        if ($placesMax === null) {
            return null;
        }

        return max(0, $placesMax - max(0, $currentInscriptions));
    }

    public function canRegister(
        Evenement $evenement,
        bool $isOwner,
        bool $alreadyRegistered,
        int $currentInscriptions,
        ?\DateTimeInterface $now = null
    ): bool {
        if ($isOwner || $alreadyRegistered) {
            return false;
        }

        if (!$this->isOpenForSubscription($evenement, $now)) {
            return false;
        }

        $remaining = $this->calculateRemainingPlaces($evenement, $currentInscriptions);

        return $remaining === null || $remaining > 0;
    }

    public function create(Evenement $evenement): Evenement
    {
        $this->validate($evenement);
        $em = $this->requireEntityManager();
        $em->persist($evenement);
        $em->flush();

        return $evenement;
    }

    public function findById(int $id): ?Evenement
    {
        $result = $this->requireEntityManager()
            ->getRepository(Evenement::class)
            ->find($id);

        return $result instanceof Evenement ? $result : null;
    }

    public function update(Evenement $evenement): Evenement
    {
        $this->validate($evenement);
        $this->requireEntityManager()->flush();

        return $evenement;
    }

    public function delete(Evenement $evenement): void
    {
        $em = $this->requireEntityManager();
        $em->remove($evenement);
        $em->flush();
    }

    private function validateDates(Evenement $evenement): void
    {
        $dateDebut = $evenement->getDateDebut();
        $dateFin = $evenement->getDateFin();

        if ($dateDebut === null) {
            throw new InvalidArgumentException('La date de debut est obligatoire.');
        }

        if ($dateFin === null) {
            throw new InvalidArgumentException('La date de fin est obligatoire.');
        }

        if ($dateFin < $dateDebut) {
            throw new InvalidArgumentException('La date de fin doit etre posterieure a la date de debut.');
        }
    }

    private function validatePlacesMax(Evenement $evenement): void
    {
        $placesMax = $evenement->getPlacesMax();

        if ($placesMax === null) {
            return; // facultatif
        }

        if ($placesMax <= 0) {
            throw new InvalidArgumentException('Le nombre de places doit etre superieur a zero.');
        }

        if ($placesMax > 10000) {
            throw new InvalidArgumentException('Le nombre de places ne peut pas depasser 10000.');
        }
    }

    private function validateTarif(Evenement $evenement): void
{
    $tarif = $evenement->getTarif();

    if ($tarif === null) {
        return;
    }

    if ($tarif < 0) {
        throw new InvalidArgumentException('Le tarif ne peut pas etre negatif.');
    }

    if ($tarif > 10000) {
        throw new InvalidArgumentException('Le tarif ne peut pas depasser 10000 TND.');
    }
}

private function validateTitre(Evenement $evenement): void
{
    $titre = trim((string) $evenement->getTitre());

    if ($titre === '') {
        throw new InvalidArgumentException('Le titre est obligatoire.');
    }

    if (mb_strlen($titre) < 5) {
        throw new InvalidArgumentException('Le titre doit contenir au moins 5 caracteres.');
    }

    if (mb_strlen($titre) > 200) {
        throw new InvalidArgumentException('Le titre ne peut pas depasser 200 caracteres.');
    }
}

private function requireEntityManager(): EntityManagerInterface
{
    if ($this->entityManager === null) {
        throw new LogicException('EntityManager indisponible pour operation CRUD.');
    }

    return $this->entityManager;
}
}
