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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $dateInscription;

    #[ORM\Column(length: 50)]
    private string $statut = 'en_attente';

    #[ORM\Column]
    private bool $present = false;


    #[ORM\ManyToOne]
#[ORM\JoinColumn(nullable: true)]
private ?GroupeCible $groupeCible = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $modifieLe = null;

    public function __construct()
{
    $this->dateInscription = new \DateTimeImmutable();
    $this->creeLe = new \DateTimeImmutable();
    $this->present = false;
    $this->statut = 'en_attente'; 
}

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
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

    public function getDateInscription(): \DateTimeImmutable
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = self::toImmutable($dateInscription);

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPresent(): bool
    {
        return $this->present;
    }

    public function setPresent(bool $present): static
    {
        $this->present = $present;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTimeInterface $creeLe): static
    {
        $this->creeLe = self::toImmutable($creeLe);

        return $this;
    }

    public function getModifieLe(): ?\DateTimeImmutable
    {
        return $this->modifieLe;
    }

    public function setModifieLe(?\DateTimeInterface $modifieLe): static
    {
        $this->modifieLe = $modifieLe !== null ? self::toImmutable($modifieLe) : null;

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

private static function toImmutable(\DateTimeInterface $value): \DateTimeImmutable
{
    return $value instanceof \DateTimeImmutable ? $value : \DateTimeImmutable::createFromInterface($value);
}

}
