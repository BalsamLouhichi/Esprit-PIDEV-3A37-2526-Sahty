<?php

namespace App\Entity;

use App\Repository\AdministrateurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdministrateurRepository::class)]
#[ORM\Table(name: 'administrateur')]
class Administrateur extends Utilisateur
{
    public function __construct()
    {
        parent::__construct();
        $this->setRole(self::ROLE_SIMPLE_ADMIN);
    }

    // Vous pouvez ajouter des propriÃ©tÃ©s spÃ©cifiques Ã  l'administrateur si nÃ©cessaire
    // Par exemple : niveau d'accÃ¨s, dÃ©partement, etc.
}
