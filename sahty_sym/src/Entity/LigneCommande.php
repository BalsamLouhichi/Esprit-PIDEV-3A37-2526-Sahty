<?php
// src/Entity/LigneCommande.php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignesCommandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    #[ORM\Column]
    private ?int $quantite = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $prixUnitaire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $sousTotal = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): self
    {
        $this->commande = $commande;
        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): self
    {
        $this->produit = $produit;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;
        $this->calculerSousTotal();
        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): self
    {
        $this->prixUnitaire = $prixUnitaire;
        $this->calculerSousTotal();
        return $this;
    }

    public function getSousTotal(): ?string
    {
        return $this->sousTotal;
    }

    public function setSousTotal(string $sousTotal): self
    {
        $this->sousTotal = $sousTotal;
        return $this;
    }

    public function calculerSousTotal(): self
    {
        if ($this->prixUnitaire !== null && $this->quantite !== null && is_numeric($this->prixUnitaire)) {
            /** @var numeric-string $prixUnitaire */
            $prixUnitaire = $this->prixUnitaire;
            $this->sousTotal = bcmul($prixUnitaire, (string) $this->quantite, 2);
        }
        return $this;
    }
}
