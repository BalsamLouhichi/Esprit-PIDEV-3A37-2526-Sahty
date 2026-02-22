<?php

namespace App\Entity;

use App\Repository\ResponsableLaboratoireRepository;
use Doctrine\ORM\Mapping as ORM;

// src/Entity/ResponsableLaboratoire.php
#[ORM\Entity(repositoryClass: ResponsableLaboratoireRepository::class)]
#[ORM\Table(name: 'responsable_laboratoire')]
class ResponsableLaboratoire extends Utilisateur
{
    #[ORM\OneToOne(inversedBy: 'responsable', targetEntity: Laboratoire::class)]
    #[ORM\JoinColumn(name: 'laboratoire_id', referencedColumnName: 'id', nullable: true)]
    private ?Laboratoire $laboratoire = null;

    public function __construct()
    {
        parent::__construct();
        $this->setRole(self::ROLE_SIMPLE_RESPONSABLE_LABO);
    }

    public function getLaboratoire(): ?Laboratoire
    {
        return $this->laboratoire;
    }

    public function setLaboratoire(?Laboratoire $laboratoire): self
    {
        $this->laboratoire = $laboratoire;
        return $this;
    }

    // Helper method to get the ID directly
    public function getLaboratoireId(): ?int
    {
        return $this->laboratoire?->getId();
    }
}