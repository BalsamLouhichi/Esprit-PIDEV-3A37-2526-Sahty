<?php

namespace App\Entity;

use App\Repository\MedecinRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MedecinRepository::class)]
#[ORM\Table(name: 'medecin')]
class Medecin extends Utilisateur
{
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $specialite = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $anneeExperience = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $grade = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresseCabinet = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephoneCabinet = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nomEtablissement = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroUrgence = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentPdf = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $disponibilite = null;

    public function __construct()
    {
        parent::__construct();
        $this->setRole(self::ROLE_SIMPLE_MEDECIN); // Utilisation de la constante
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(?string $specialite): self
    {
        $this->specialite = $specialite;
        return $this;
    }

    public function getAnneeExperience(): ?int
    {
        return $this->anneeExperience;
    }

    public function setAnneeExperience(?int $anneeExperience): self
    {
        $this->anneeExperience = $anneeExperience;
        return $this;
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function setGrade(?string $grade): self
    {
        $this->grade = $grade;
        return $this;
    }

    public function getAdresseCabinet(): ?string
    {
        return $this->adresseCabinet;
    }

    public function setAdresseCabinet(?string $adresseCabinet): self
    {
        $this->adresseCabinet = $adresseCabinet;
        return $this;
    }

    public function getTelephoneCabinet(): ?string
    {
        return $this->telephoneCabinet;
    }

    public function setTelephoneCabinet(?string $telephoneCabinet): self
    {
        $this->telephoneCabinet = $telephoneCabinet;
        return $this;
    }

    public function getNomEtablissement(): ?string
    {
        return $this->nomEtablissement;
    }

    public function setNomEtablissement(?string $nomEtablissement): self
    {
        $this->nomEtablissement = $nomEtablissement;
        return $this;
    }

    public function getNumeroUrgence(): ?string
    {
        return $this->numeroUrgence;
    }

    public function setNumeroUrgence(?string $numeroUrgence): self
    {
        $this->numeroUrgence = $numeroUrgence;
        return $this;
    }

    public function getDocumentPdf(): ?string
    {
        return $this->documentPdf;
    }

    public function setDocumentPdf(?string $documentPdf): self
    {
        $this->documentPdf = $documentPdf;
        return $this;
    }

    public function getDisponibilite(): ?string
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?string $disponibilite): self
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    /**
     * Méthode pour obtenir le nom complet avec spécialité
     */
    public function getNomCompletAvecSpecialite(): string
    {
        $nomComplet = parent::getNomComplet();
        if ($this->specialite) {
            $nomComplet .= " (" . $this->specialite . ")";
        }
        return $nomComplet;
    }
}