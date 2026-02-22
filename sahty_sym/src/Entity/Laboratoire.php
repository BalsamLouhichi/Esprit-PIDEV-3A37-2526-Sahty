<?php
// src/Entity/Laboratoire.php

namespace App\Entity;

use App\Repository\LaboratoireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LaboratoireRepository::class)]
#[ORM\Table(name: "laboratoire")]
class Laboratoire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Regex(
        pattern: "/^[A-Za-z]+(?:[ '\\-][A-Za-z]+)*$/",
        message: 'Le nom doit contenir uniquement des lettres, espaces, tirets ou apostrophes.'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Regex(
        pattern: "/^[A-Za-z]+(?:[ '\\-,][A-Za-z]+)*$/",
        message: 'La ville doit contenir uniquement des lettres, espaces, tirets, apostrophes ou virgules.'
    )]
    private ?string $ville = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    #[Assert\Regex(
        pattern: "/^[A-Za-z0-9 '\\-,]+$/",
        message: 'L\'adresse doit contenir uniquement lettres, chiffres, espaces, tirets, apostrophes ou virgules.'
    )]
    private ?string $adresse = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le telephone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(?:\+216\s?)?[0-9]{8}$/',
        message: 'Le telephone doit contenir 8 chiffres (optionnellement avec +216).'
    )]
    private ?string $telephone = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La latitude est obligatoire.')]
    #[Assert\Range(
        min: -90,
        max: 90,
        notInRangeMessage: 'La latitude doit etre entre -90 et 90.'
    )]
    private ?float $latitude = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La longitude est obligatoire.')]
    #[Assert\Range(
        min: -180,
        max: 180,
        notInRangeMessage: 'La longitude doit etre entre -180 et 180.'
    )]
    private ?float $longitude = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    #[ORM\Column]
    private ?\DateTime $cree_le = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroAgrement = null;

    /**
     * @var Collection<int, DemandeAnalyse>
     */
    #[ORM\OneToMany(targetEntity: DemandeAnalyse::class, mappedBy: 'laboratoire')]
    private Collection $demandeAnalyses;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, LaboratoireTypeAnalyse>
     */
    #[ORM\OneToMany(targetEntity: LaboratoireTypeAnalyse::class, mappedBy: 'laboratoire', cascade: ['persist', 'remove'])]
    private Collection $laboratoireTypeAnalyses;

    #[ORM\OneToOne(targetEntity: ResponsableLaboratoire::class, mappedBy: 'laboratoire')]
    private ?ResponsableLaboratoire $responsable = null;

    public function __construct()
    {
        $this->demandeAnalyses = new ArrayCollection();
        $this->laboratoireTypeAnalyses = new ArrayCollection();
        $this->cree_le = new \DateTime();
        $this->disponible = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getCreeLe(): ?\DateTime
    {
        return $this->cree_le;
    }

    public function setCreeLe(\DateTime $cree_le): static
    {
        $this->cree_le = $cree_le;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getNumeroAgrement(): ?string
    {
        return $this->numeroAgrement;
    }

    public function setNumeroAgrement(?string $numeroAgrement): static
    {
        $this->numeroAgrement = $numeroAgrement;
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
            $demandeAnalyse->setLaboratoire($this);
        }

        return $this;
    }

    public function removeDemandeAnalyse(DemandeAnalyse $demandeAnalyse): static
    {
        if ($this->demandeAnalyses->removeElement($demandeAnalyse)) {
            if ($demandeAnalyse->getLaboratoire() === $this) {
                $demandeAnalyse->setLaboratoire(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Collection<int, LaboratoireTypeAnalyse>
     */
    public function getLaboratoireTypeAnalyses(): Collection
    {
        return $this->laboratoireTypeAnalyses;
    }

    public function addLaboratoireTypeAnalysis(LaboratoireTypeAnalyse $laboratoireTypeAnalysis): static
    {
        if (!$this->laboratoireTypeAnalyses->contains($laboratoireTypeAnalysis)) {
            $this->laboratoireTypeAnalyses->add($laboratoireTypeAnalysis);
            $laboratoireTypeAnalysis->setLaboratoire($this);
        }

        return $this;
    }

    public function removeLaboratoireTypeAnalysis(LaboratoireTypeAnalyse $laboratoireTypeAnalysis): static
    {
        if ($this->laboratoireTypeAnalyses->removeElement($laboratoireTypeAnalysis)) {
            if ($laboratoireTypeAnalysis->getLaboratoire() === $this) {
                $laboratoireTypeAnalysis->setLaboratoire(null);
            }
        }

        return $this;
    }

    // NOUVELLES MÉTHODES POUR LA RELATION AVEC RESPONSABLE

    public function getResponsable(): ?ResponsableLaboratoire
    {
        return $this->responsable;
    }

    public function setResponsable(?ResponsableLaboratoire $responsable): static
    {
        // Gestion de la relation bidirectionnelle
        if ($responsable === null && $this->responsable !== null) {
            $this->responsable->setLaboratoire(null);
        }

        if ($responsable !== null && $responsable->getLaboratoire() !== $this) {
            $responsable->setLaboratoire($this);
        }

        $this->responsable = $responsable;
        return $this;
    }

    /**
     * Méthode pour vérifier si le laboratoire a un responsable
     */
    public function hasResponsable(): bool
    {
        return $this->responsable !== null;
    }

    /**
     * Méthode pour obtenir le nom du responsable
     */
    public function getNomResponsable(): ?string
    {
        return $this->responsable ? $this->responsable->getNomComplet() : null;
    }

    /**
     * Méthode pour obtenir l'email du responsable
     */
    public function getEmailResponsable(): ?string
    {
        return $this->responsable ? $this->responsable->getEmail() : null;
    }

    /**
     * Méthode pour obtenir le téléphone du responsable
     */
    public function getTelephoneResponsable(): ?string
    {
        return $this->responsable ? $this->responsable->getTelephone() : null;
    }

    // Méthode pour faciliter l'affichage
    public function __toString(): string
    {
        return $this->nom ?? 'Laboratoire';
    }

    /**
     * Méthode pour obtenir l'adresse complète
     */
    public function getAdresseComplete(): string
    {
        return sprintf('%s, %s', $this->adresse, $this->ville);
    }

    /**
     * Méthode pour vérifier si le laboratoire est actif
     * (alias pour isDisponible pour la compatibilité)
     */
    public function isEstActif(): bool
    {
        return $this->disponible === true;
    }
}