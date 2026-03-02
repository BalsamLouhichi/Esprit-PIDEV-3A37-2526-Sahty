<?php
// src/Service/EvenementManager.php

namespace App\Service;

use App\Entity\Evenement;
use InvalidArgumentException;

class EvenementManager
{
    public function validate(Evenement $evenement): bool
    {
        $this->validateDates($evenement);
        $this->validatePlacesMax($evenement);
        $this->validateTarif($evenement);
        $this->validateTitre($evenement);

        return true;
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
}
