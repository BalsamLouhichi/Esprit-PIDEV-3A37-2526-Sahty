<?php

namespace App\Entity;

use App\Repository\FicheMedicaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheMedicaleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FicheMedicale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $antecedents = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $allergies = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $traitement_en_cours = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $taille = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $poids = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $imc = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $categorieImc = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $diagnostic = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $traitement_prescrit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    // ✅ CORRECTION : Utiliser camelCase avec mapping vers snake_case en BDD
    #[ORM\Column(name: 'cree_le', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $creeLe = null;

    #[ORM\Column(name: 'modifie_le', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $modifieLe = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'fichesMedicales')]
    #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'id', nullable: true)]
    private ?Patient $patient = null;

    #[ORM\OneToOne(inversedBy: 'ficheMedicale')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?RendezVous $rendezVous = null;

    // ==================== GETTERS ET SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAntecedents(): ?string
    {
        return $this->antecedents;
    }

    public function setAntecedents(?string $antecedents): static
    {
        $this->antecedents = $antecedents;
        return $this;
    }

    public function getAllergies(): ?string
    {
        return $this->allergies;
    }

    public function setAllergies(?string $allergies): static
    {
        $this->allergies = $allergies;
        return $this;
    }

    public function getTraitementEnCours(): ?string
    {
        return $this->traitement_en_cours;
    }

    public function setTraitementEnCours(?string $traitement_en_cours): static
    {
        $this->traitement_en_cours = $traitement_en_cours;
        return $this;
    }

    public function getTaille(): ?string
    {
        return $this->taille;
    }

    public function setTaille(?string $taille): static
    {
        $this->taille = $taille;
        return $this;
    }

    public function getPoids(): ?string
    {
        return $this->poids;
    }

    public function setPoids(?string $poids): static
    {
        $this->poids = $poids;
        return $this;
    }

    public function getImc(): ?float
    {
        return $this->imc;
    }

    public function setImc(?float $imc): static
    {
        $this->imc = $imc;
        return $this;
    }

    public function getCategorieImc(): ?string
    {
        return $this->categorieImc;
    }

    public function setCategorieImc(?string $categorieImc): static
    {
        $this->categorieImc = $categorieImc;
        return $this;
    }

    public function getDiagnostic(): ?string
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(?string $diagnostic): static
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getTraitementPrescrit(): ?string
    {
        return $this->traitement_prescrit;
    }

    public function setTraitementPrescrit(?string $traitement_prescrit): static
    {
        $this->traitement_prescrit = $traitement_prescrit;
        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;
        return $this;
    }

    // ✅ CORRECTION : Getters/Setters en camelCase
    public function getCreeLe(): ?\DateTimeInterface
    {
        return $this->creeLe;
    }

    public function setCreeLe(?\DateTimeInterface $creeLe): static
    {
        $this->creeLe = $creeLe;
        return $this;
    }

    public function getModifieLe(): ?\DateTimeInterface
    {
        return $this->modifieLe;
    }

    public function setModifieLe(?\DateTimeInterface $modifieLe): static
    {
        $this->modifieLe = $modifieLe;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

    public function getRendezVous(): ?RendezVous
    {
        return $this->rendezVous;
    }

    public function setRendezVous(?RendezVous $rendezVous): static
    {
        $this->rendezVous = $rendezVous;
        return $this;
    }

    // ==================== LIFECYCLE CALLBACKS ====================
    
    #[ORM\PrePersist]
    public function setCreeLeValue(): void
    {
        if ($this->creeLe === null) {
            $this->creeLe = new \DateTime();
        }
        if ($this->statut === null) {
            $this->statut = 'actif';
        }
    }

    #[ORM\PreUpdate]
    public function setModifieLeValue(): void
    {
        $this->modifieLe = new \DateTime();
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Calcule automatiquement l'IMC et sa catégorie
     */
    public function calculerImc(): void
    {
        if ($this->taille && $this->poids && (float)$this->taille > 0) {
            $tailleEnMetres = (float)$this->taille;
            $poidsEnKg = (float)$this->poids;

            $imc = $poidsEnKg / ($tailleEnMetres * $tailleEnMetres);
            $this->imc = round($imc, 2);

            // Déterminer la catégorie
            if ($imc < 18.5) {
                $this->categorieImc = 'Maigreur';
            } elseif ($imc < 25) {
                $this->categorieImc = 'Normal';
            } elseif ($imc < 30) {
                $this->categorieImc = 'Surpoids';
            } else {
                $this->categorieImc = 'Obésité';
            }
        } else {
            $this->imc = null;
            $this->categorieImc = null;
        }
    }

    /**
     * Retourne l'âge du patient si disponible
     */
    public function getAgePatient(): ?int
    {
        if ($this->patient && $this->patient->getDateNaissance()) {
            $now = new \DateTime();
            $interval = $this->patient->getDateNaissance()->diff($now);
            return $interval->y;
        }
        return null;
    }

    /**
     * Vérifie si la fiche est récente (moins de 30 jours)
     */
    public function isRecente(): bool
    {
        if ($this->creeLe === null) {
            return false;
        }
        
        $now = new \DateTime();
        $interval = $this->creeLe->diff($now);
        return $interval->days < 30;
    }

    /**
     * Retourne un résumé de la fiche
     */
    public function getResume(): string
    {
        $patient = $this->patient ? $this->patient->getNom() . ' ' . $this->patient->getPrenom() : 'Patient inconnu';
        $date = $this->creeLe ? $this->creeLe->format('d/m/Y') : 'Date inconnue';
        
        return sprintf('Fiche de %s - %s', $patient, $date);
    }

    // Méthode toString pour affichage
    public function __toString(): string
    {
        return $this->getResume();
    }
}
