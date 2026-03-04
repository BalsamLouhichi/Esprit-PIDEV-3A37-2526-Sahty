<?php

namespace App\Service;

use App\Entity\GroupeCible;
use InvalidArgumentException;

class GroupeCibleManager
{
    public function validate(GroupeCible $groupe): bool
    {
        $this->validateNom($groupe);
        $this->validateType($groupe);
        $this->validateCritereOptionnel($groupe);

        return true;
    }

    private function validateNom(GroupeCible $groupe): void
    {
        $nom = trim((string) $groupe->getNom());

        if ($nom === '') {
            throw new InvalidArgumentException('Le nom du groupe cible est obligatoire.');
        }

        if (mb_strlen($nom) < 3) {
            throw new InvalidArgumentException('Le nom du groupe cible doit contenir au moins 3 caracteres.');
        }
    }

    private function validateType(GroupeCible $groupe): void
    {
        $type = trim((string) $groupe->getType());

        if ($type === '') {
            throw new InvalidArgumentException('Le type du groupe cible est obligatoire.');
        }

        $typesAutorises = ['patient', 'medecin', 'laboratoire', 'paramedical'];

        if (!in_array($type, $typesAutorises, true)) {
            throw new InvalidArgumentException('Le type du groupe cible est invalide.');
        }
    }

    private function validateCritereOptionnel(GroupeCible $groupe): void
    {
        $critere = $groupe->getCritereOptionnel();

        if ($critere === null) {
            return;
        }

        if (mb_strlen(trim($critere)) > 255) {
            throw new InvalidArgumentException('Le critere optionnel ne doit pas depasser 255 caracteres.');
        }
    }
}
