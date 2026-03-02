<?php
// src/Service/rendezvousManager.php

namespace App\Service;

use InvalidArgumentException;

class rendezvousManager
{
    public const MIN_MOTIF_LENGTH = 5;
    public const MAX_MOTIF_LENGTH = 1000;

    /**
     * Valide et normalise le motif saisi.
     */
    public function validateMotif(string|null $motif): string
    {
        $motif = trim((string) $motif);

        if ($motif === '') {
            throw new InvalidArgumentException('Le motif du rendez-vous ne doit pas être vide.');
        }

        $len = mb_strlen($motif, 'UTF-8');

        if ($len < self::MIN_MOTIF_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Le motif doit contenir au moins %d caractères.',
                self::MIN_MOTIF_LENGTH
            ));
        }

        if ($len > self::MAX_MOTIF_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Le motif ne peut pas dépasser %d caractères.',
                self::MAX_MOTIF_LENGTH
            ));
        }

        return $motif;
    }
}
