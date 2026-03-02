<?php

namespace App\Service;

use App\Entity\InscriptionEvenement;
use InvalidArgumentException;

class InscriptionEvenementManager
{
    public function validate(InscriptionEvenement $inscription): bool
    {
        // Regle 1: evenement et utilisateur obligatoires
        if ($inscription->getEvenement() === null || $inscription->getUtilisateur() === null) {
            throw new InvalidArgumentException('L\'evenement et l\'utilisateur sont obligatoires.');
        }

        // Regle 2: date d'inscription non future
        $dateInscription = $inscription->getDateInscription();
        if ($dateInscription === null) {
            throw new InvalidArgumentException('La date d\'inscription est obligatoire.');
        }

        $now = new \DateTime();
        if ($dateInscription > $now) {
            throw new InvalidArgumentException('La date d\'inscription ne peut pas etre dans le futur.');
        }

        // Regle 3: statut valide + coherence presence/statut
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
}
