<?php

namespace App\Entity;

use App\Repository\InscriptionEvenementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InscriptionEvenementRepository::class)]
class InscriptionEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column]
    private ?\DateTime $dateInscription = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?bool $present = null;


    #[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: true)]
private ?GroupeCible $groupeCible = null;

    #[ORM\Column]
    private ?\DateTime $creeLe = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $modifieLe = null;

    public function __construct()
{
    $this->dateInscription = new \DateTime();
    $this->creeLe = new \DateTime();
    $this->present = false;
    $this->statut = 'en_attente'; 
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getDateInscription(): ?\DateTime
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTime $dateInscription): static
    {
        $this->dateInscription = $dateInscription;

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

    public function isPresent(): ?bool
    {
        return $this->present;
    }

    public function setPresent(bool $present): static
    {
        $this->present = $present;

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

    public function getGroupeCible(): ?GroupeCible
{
    return $this->groupeCible;
}

public function setGroupeCible(?GroupeCible $groupeCible): self
{
    $this->groupeCible = $groupeCible;

    return $this;
}

}
