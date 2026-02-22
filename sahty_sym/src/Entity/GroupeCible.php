<?php

namespace App\Entity;

use App\Repository\GroupeCibleRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: GroupeCibleRepository::class)]
class GroupeCible
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $critereOptionnel = null;

    #[ORM\ManyToMany(targetEntity: Evenement::class, mappedBy: 'groupeCibles')]
    private Collection $evenements;

    public function __construct()
    {
        $this->evenements = new ArrayCollection();
    }

    public function getEvenements(): Collection
{
    return $this->evenements;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCritereOptionnel(): ?string
    {
        return $this->critereOptionnel;
    }

    public function setCritereOptionnel(?string $critereOptionnel): static
    {
        $this->critereOptionnel = $critereOptionnel;

        return $this;
    }

    public function addEvenement(Evenement $evenement): self
{
    if (!$this->evenements->contains($evenement)) {
        $this->evenements->add($evenement);
        $evenement->addGroupeCible($this);
    }
    return $this;
}

public function removeEvenement(Evenement $evenement): self
{
    if ($this->evenements->contains($evenement)) {
        $this->evenements->removeElement($evenement);
        $evenement->removeGroupeCible($this);
    }
    return $this;
}
}
