<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
#[ORM\Table(name: 'patient')]
class Patient extends Utilisateur
{
    public const ROLE_SIMPLE_PATIENT = 'patient';

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $groupeSanguin = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $contactUrgence = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $sexe = null;

    /**
     * @var Collection<int, FicheMedicale>
     */
    #[ORM\OneToMany(targetEntity: FicheMedicale::class, mappedBy: 'patient', fetch: 'EXTRA_LAZY', orphanRemoval: true, cascade: ['persist'])]
    private Collection $ficheMedicales;

    /**
     * @var Collection<int, RendezVous>
     */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'patient', fetch: 'EXTRA_LAZY')]
    private Collection $rendezVous;

    /**
     * @var Collection<int, DemandeAnalyse>
     */
    #[ORM\OneToMany(targetEntity: DemandeAnalyse::class, mappedBy: 'patient', fetch: 'EXTRA_LAZY')]
    private Collection $demandeAnalyses;

    public function __construct()
    {
        parent::__construct();
        $this->setRole(self::ROLE_SIMPLE_PATIENT);
        $this->ficheMedicales = new ArrayCollection();
        $this->rendezVous = new ArrayCollection();
        $this->demandeAnalyses = new ArrayCollection();
    }

    public function getGroupeSanguin(): ?string
    {
        return $this->groupeSanguin;
    }

    public function setGroupeSanguin(?string $groupeSanguin): self
    {
        $this->groupeSanguin = $groupeSanguin;
        return $this;
    }

    public function getContactUrgence(): ?string
    {
        return $this->contactUrgence;
    }

    public function setContactUrgence(?string $contactUrgence): self
    {
        $this->contactUrgence = $contactUrgence;
        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): self
    {
        $this->sexe = $sexe;
        return $this;
    }

    /**
     * @return Collection<int, FicheMedicale>
     */
    public function getFicheMedicales(): Collection
    {
        return $this->ficheMedicales;
    }

    public function addFicheMedicale(FicheMedicale $ficheMedicale): static
    {
        if (!$this->ficheMedicales->contains($ficheMedicale)) {
            $this->ficheMedicales->add($ficheMedicale);
            $ficheMedicale->setPatient($this);
        }
        return $this;
    }

    public function removeFicheMedicale(FicheMedicale $ficheMedicale): static
    {
        $this->ficheMedicales->removeElement($ficheMedicale);
        return $this;
    }

    /**
     * @return Collection<int, RendezVous>
     */
    public function getRendezVous(): Collection
    {
        return $this->rendezVous;
    }

    public function addRendezVous(RendezVous $rendezVous): static
    {
        if (!$this->rendezVous->contains($rendezVous)) {
            $this->rendezVous->add($rendezVous);
            $rendezVous->setPatient($this);
        }
        return $this;
    }

    public function removeRendezVous(RendezVous $rendezVous): static
    {
        if ($this->rendezVous->removeElement($rendezVous)) {
            if ($rendezVous->getPatient() === $this) {
                $rendezVous->setPatient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, DemandeAnalyse>
     */
    public function getDemandeAnalyses(): Collection
    {
        return $this->demandeAnalyses;
    }

    public function addDemandeAnalyse(DemandeAnalyse $demandeAnalyse): static
    {
        if (!$this->demandeAnalyses->contains($demandeAnalyse)) {
            $this->demandeAnalyses->add($demandeAnalyse);
            $demandeAnalyse->setPatient($this);
        }
        return $this;
    }

    public function removeDemandeAnalyse(DemandeAnalyse $demandeAnalyse): static
    {
        if ($this->demandeAnalyses->removeElement($demandeAnalyse)) {
            if ($demandeAnalyse->getPatient() === $this) {
                $demandeAnalyse->setPatient(null);
            }
        }
        return $this;
    }

    /**
     * Calcul de l'âge du patient
     */
    public function getAge(): ?int
    {
        if (!$this->getDateNaissance()) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->getDateNaissance());
        return $interval->y;
    }
}
