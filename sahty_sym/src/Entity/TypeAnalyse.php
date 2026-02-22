<?php

namespace App\Entity;

use App\Repository\TypeAnalyseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeAnalyseRepository::class)]
class TypeAnalyse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $actif = null;

    #[ORM\Column]
    private ?\DateTime $cree_le = null;

    /**
     * @var Collection<int, LaboratoireTypeAnalyse>
     */
    #[ORM\OneToMany(targetEntity: LaboratoireTypeAnalyse::class, mappedBy: 'typeAnalyse')]
    private Collection $laboratoireTypeAnalyses;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $categorie = null;

    public function __construct()
    {
        $this->laboratoireTypeAnalyses = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

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
            $laboratoireTypeAnalysis->setTypeAnalyse($this);
        }

        return $this;
    }

    public function removeLaboratoireTypeAnalysis(LaboratoireTypeAnalyse $laboratoireTypeAnalysis): static
    {
        if ($this->laboratoireTypeAnalyses->removeElement($laboratoireTypeAnalysis)) {
            // set the owning side to null (unless already changed)
            if ($laboratoireTypeAnalysis->getTypeAnalyse() === $this) {
                $laboratoireTypeAnalysis->setTypeAnalyse(null);
            }
        }

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }
    public function __toString(): string
    {
        return (string) $this->getNom();
    }
}
