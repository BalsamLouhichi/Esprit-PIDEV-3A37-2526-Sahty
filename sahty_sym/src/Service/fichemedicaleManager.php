<?php
// src/Service/fichemedicaleManager.php

namespace App\Service;

use App\Entity\FicheMedicale;
use InvalidArgumentException;

class fichemedicaleManager
{
    public const MIN_TAILLE = 0.5; // metres
    public const MAX_TAILLE = 2.5; // metres
    public const MIN_POIDS = 1.0;  // kg
    public const MAX_POIDS = 500.0; // kg

    /**
     * Normalise un texte facultatif (retourne null si vide).
     */
    public function normalizeOptionalText(string|null $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Valide et normalise une taille en metres.
     * Retourne une chaine decimal normalisee (ex: "1.75") ou null.
     */
    public function validateTaille(string|null $taille): ?string
    {
        return $this->validateDecimalInRange(
            $taille,
            self::MIN_TAILLE,
            self::MAX_TAILLE,
            'La taille doit etre comprise entre 0.5 m et 2.5 m.'
        );
    }

    /**
     * Valide et normalise un poids en kg.
     * Retourne une chaine decimal normalisee (ex: "70.00") ou null.
     */
    public function validatePoids(string|null $poids): ?string
    {
        return $this->validateDecimalInRange(
            $poids,
            self::MIN_POIDS,
            self::MAX_POIDS,
            'Le poids doit etre compris entre 1 kg et 500 kg.'
        );
    }

    /**
     * Applique les valeurs valides sur la fiche puis recalcule l'IMC.
     */
    public function applyVitals(FicheMedicale $ficheMedicale, string|null $taille, string|null $poids): FicheMedicale
    {
        $ficheMedicale->setTaille($this->validateTaille($taille));
        $ficheMedicale->setPoids($this->validatePoids($poids));
        $ficheMedicale->calculerImc();

        return $ficheMedicale;
    }

    private function validateDecimalInRange(
        string|null $value,
        float $min,
        float $max,
        string $message
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            throw new InvalidArgumentException('La valeur doit etre numerique.');
        }

        $number = (float) $normalized;
        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException($message);
        }

        return number_format($number, 2, '.', '');
    }
}
