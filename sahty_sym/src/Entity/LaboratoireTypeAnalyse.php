<?php

namespace App\Entity;

use App\Repository\LaboratoireTypeAnalyseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LaboratoireTypeAnalyseRepository::class)]
class LaboratoireTypeAnalyse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'laboratoireTypeAnalyses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Laboratoire $laboratoire = null;

    #[ORM\ManyToOne(inversedBy: 'laboratoireTypeAnalyses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeAnalyse $typeAnalyse = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prix = null;

    #[ORM\Column(nullable: true)]
    private ?int $delaiResultatHeures = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditions = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLaboratoire(): ?Laboratoire
    {
        return $this->laboratoire;
    }

    public function setLaboratoire(?Laboratoire $laboratoire): static
    {
        $this->laboratoire = $laboratoire;

        return $this;
    }

    public function getTypeAnalyse(): ?TypeAnalyse
    {
        return $this->typeAnalyse;
    }

    public function setTypeAnalyse(?TypeAnalyse $typeAnalyse): static
    {
        $this->typeAnalyse = $typeAnalyse;

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

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getDelaiResultatHeures(): ?int
    {
        return $this->delaiResultatHeures;
    }

    public function setDelaiResultatHeures(?int $delaiResultatHeures): static
    {
        $this->delaiResultatHeures = $delaiResultatHeures;

        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }
}