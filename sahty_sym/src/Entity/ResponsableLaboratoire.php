<?php

namespace App\Entity;

use App\Repository\ResponsableLaboratoireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResponsableLaboratoireRepository::class)]
#[ORM\Table(name: 'responsable_laboratoire')]
class ResponsableLaboratoire extends Utilisateur
{
    #[ORM\OneToOne(targetEntity: Laboratoire::class, inversedBy: 'responsable')]
    #[ORM\JoinColumn(name: 'laboratoire_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
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
        // Désassocier l'ancien laboratoire
        if ($this->laboratoire !== null && $this->laboratoire->getResponsable() === $this) {
            $this->laboratoire->setResponsable(null);
        }

        // Associer le nouveau laboratoire
        $this->laboratoire = $laboratoire;

        if ($laboratoire !== null && $laboratoire->getResponsable() !== $this) {
            $laboratoire->setResponsable($this);
        }

        return $this;
    }

    /**
     * Compatibilité avec l'ancien code utilisant laboratoireId
     */
    public function getLaboratoireId(): ?int
    {
        return $this->laboratoire ? $this->laboratoire->getId() : null;
    }

    public function setLaboratoireId(?int $laboratoireId): self
    {
        // Déprécié mais gardé pour compatibilité avec SignupController
        return $this;
    }

    public function getNomLaboratoire(): ?string
    {
        return $this->laboratoire ? $this->laboratoire->getNom() : null;
    }

    public function getAdresseLaboratoire(): ?string
    {
        return $this->laboratoire ? $this->laboratoire->getAdresseComplete() : null;
    }

    public function getTelephoneLaboratoire(): ?string
    {
        return $this->laboratoire ? $this->laboratoire->getTelephone() : null;
    }

    public function hasLaboratoire(): bool
    {
        return $this->laboratoire !== null;
    }
}
