<?php

namespace App\Service;

use App\Entity\Laboratoire;
use InvalidArgumentException;

class LaboratoireManager
{
    public function validate(Laboratoire $laboratoire): bool
    {
        if (trim((string) $laboratoire->getNom()) === '') {
            throw new InvalidArgumentException('Le nom est obligatoire.');
        }

        if (trim((string) $laboratoire->getVille()) === '') {
            throw new InvalidArgumentException('La ville est obligatoire.');
        }

        if (trim((string) $laboratoire->getAdresse()) === '') {
            throw new InvalidArgumentException("L'adresse est obligatoire.");
        }

        $telephone = trim((string) $laboratoire->getTelephone());
        if ($telephone === '') {
            throw new InvalidArgumentException('Le telephone est obligatoire.');
        }

        if (!preg_match('/^(?:\+216\s?)?[0-9]{8}$/', $telephone)) {
            throw new InvalidArgumentException('Telephone invalide.');
        }

        $email = $laboratoire->getEmail();
        if ($email !== null && trim($email) !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email invalide.');
        }

        $latitude = $laboratoire->getLatitude();
        if ($latitude === null || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude invalide.');
        }

        $longitude = $laboratoire->getLongitude();
        if ($longitude === null || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude invalide.');
        }

        return true;
    }
}
