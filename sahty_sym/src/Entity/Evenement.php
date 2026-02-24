<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

     #[Assert\Choice(choices: ['webinaire', 'atelier', 'depistage', 'conference', 'groupe_parole'])]
    #[ORM\Column(length: 50)]
    private ?string $type = null;

   #[ORM\Column(type: 'datetime')]
private ?\DateTimeInterface $dateDebut = null;

#[ORM\Column(type: 'datetime', nullable: true)]
private ?\DateTimeInterface $dateFin = null;


    #[Assert\Choice(choices: ['en_ligne', 'presentiel', 'hybride'])]
    #[ORM\Column(length: 50)]
    private ?string $mode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $meetingPlatform = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $meetingLink = null;

    #[ORM\Column(nullable: true)]
    private ?int $placesMax = null;

    #[Assert\Choice(choices: ['brouillon', 'planifie', 'confirme', 'en_cours', 'termine', 'annule'])]
    #[ORM\Column(length: 50)]
    private ?string $statut = 'brouillon';

   #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
#[ORM\JoinColumn(
    name: 'createur_id', 
    referencedColumnName: 'id', 
    nullable: false,
    onDelete: 'RESTRICT' 
)]
private ?Utilisateur $createur = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $tarif = null;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'DT'])]
    private ?string $devise = 'DT';

    #[ORM\Column]
    private ?\DateTime $creeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $modifieLe = null;

    /**
     * @var Collection<int, InscriptionEvenement>
     */
    #[ORM\OneToMany(targetEntity: InscriptionEvenement::class, mappedBy: 'evenement', orphanRemoval: true)]
    private Collection $inscriptions;

    #[ORM\ManyToMany(targetEntity: GroupeCible::class, inversedBy: 'evenements')]
#[ORM\JoinTable(name: 'evenement_groupe_cible')]
private Collection $groupeCibles;


#[ORM\Column(type: 'string', length: 50, nullable: true)]
private ?string $statutDemande = null;

public function getStatutDemande(): ?string
{
    return $this->statutDemande;
}

public function setStatutDemande(?string $statutDemande): self
{
    $this->statutDemande = $statutDemande;

    return $this;
}



    public function __construct()
    {
        $this->inscriptions = new ArrayCollection();
        $this->groupeCibles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getMeetingPlatform(): ?string
    {
        return $this->meetingPlatform;
    }

    public function setMeetingPlatform(?string $meetingPlatform): static
    {
        $this->meetingPlatform = $meetingPlatform;

        return $this;
    }

    public function getMeetingLink(): ?string
    {
        return $this->meetingLink;
    }

    public function setMeetingLink(?string $meetingLink): static
    {
        $this->meetingLink = $meetingLink;

        return $this;
    }

    public function getPlacesMax(): ?int
    {
        return $this->placesMax;
    }

    public function setPlacesMax(?int $placesMax): static
    {
        $this->placesMax = $placesMax;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreateur(): ?Utilisateur
    {
        return $this->createur;
    }

    public function setCreateur(?Utilisateur $createur): static
    {
        $this->createur = $createur;

        return $this;
    }

    public function getTarif(): ?string
    {
        return $this->tarif;
    }

    public function setTarif(?string $tarif): static
    {
        $this->tarif = $tarif;

        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): static
    {
        $this->devise = $devise;

        return $this;
    }

    public function getCreeLe(): ?\DateTime
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTime $creeLe): static
    {
        $this->creeLe = $creeLe;

        return $this;
    }

    public function getModifieLe(): ?\DateTime
    {
        return $this->modifieLe;
    }

    public function setModifieLe(?\DateTime $modifieLe): static
    {
        $this->modifieLe = $modifieLe;

        return $this;
    }

    /**
     * @return Collection<int, InscriptionEvenement>
     */
    public function getInscriptions(): Collection
    {
        return $this->inscriptions;
    }

    public function addInscription(InscriptionEvenement $inscription): static
    {
        if (!$this->inscriptions->contains($inscription)) {
            $this->inscriptions->add($inscription);
            $inscription->setEvenement($this);
        }

        return $this;
    }

   public function removeInscription(InscriptionEvenement $inscription): static
    {
        if ($this->inscriptions->removeElement($inscription)) {
            
            if ($inscription->getEvenement() === $this) {
                $inscription->setEvenement(null);
            }
        }

        return $this;
    }

    public function getGroupeCibles(): Collection {
    return $this->groupeCibles;
}

public function addGroupeCible(GroupeCible $groupe): self {
    if (!$this->groupeCibles->contains($groupe)) {
        $this->groupeCibles->add($groupe);
    }
    return $this;
}

public function removeGroupeCible(GroupeCible $groupe): self {
    $this->groupeCibles->removeElement($groupe);
    return $this;
}

}
