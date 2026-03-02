<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $numero = '';

    #[ORM\ManyToOne(targetEntity: Produit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(targetEntity: Parapharmacie::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Parapharmacie $parapharmacie = null;

    #[ORM\Column]
    private int $quantite = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $prixUnitaire = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $prixTotal = '0.00';

    #[ORM\Column(length: 100)]
    private string $nomClient = '';

    #[ORM\Column(length: 150)]
    private string $email = '';

    #[ORM\Column(length: 30)]
    private string $telephone = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $adresseLivraison = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'en_attente';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateModification = null;

    /**
     * @var Collection<int, LigneCommande>
     */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: LigneCommande::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignesCommandes;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->numero = $this->generateNumero();
        $this->lignesCommandes = new ArrayCollection();
    }

    private function generateNumero(): string
    {
        return 'CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    // ----- Getters et Setters -----

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
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

    public function getParapharmacie(): ?Parapharmacie
    {
        return $this->parapharmacie;
    }

    public function setParapharmacie(?Parapharmacie $parapharmacie): self
    {
        $this->parapharmacie = $parapharmacie;
        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaire(): string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string $prixUnitaire): self
    {
        $this->prixUnitaire = $prixUnitaire;
        return $this;
    }

    public function getPrixTotal(): string
    {
        return $this->prixTotal;
    }

    public function setPrixTotal(string $prixTotal): self
    {
        $this->prixTotal = $prixTotal;
        return $this;
    }

    public function getNomClient(): string
    {
        return $this->nomClient;
    }

    public function setNomClient(string $nomClient): self
    {
        $this->nomClient = $nomClient;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getAdresseLivraison(): string
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(string $adresseLivraison): self
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): self
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    /**
     * @return Collection<int, LigneCommande>
     */
    public function getLignesCommandes(): Collection
    {
        return $this->lignesCommandes;
    }

    public function addLignesCommande(LigneCommande $ligneCommande): self
    {
        if (!$this->lignesCommandes->contains($ligneCommande)) {
            $this->lignesCommandes->add($ligneCommande);
            $ligneCommande->setCommande($this);
        }
        return $this;
    }

    public function removeLignesCommande(LigneCommande $ligneCommande): self
    {
        if ($this->lignesCommandes->removeElement($ligneCommande)) {
            if ($ligneCommande->getCommande() === $this) {
                $ligneCommande->setCommande(null);
            }
        }
        return $this;
    }

    // Méthodes utilitaires
    public function calculerPrixTotal(): void
    {
        $this->prixTotal = number_format(((float) $this->prixUnitaire) * $this->quantite, 2, '.', '');
    }

    public function getStatutLibelle(): string
    {
        $statuts = [
            'en_attente' => 'En attente',
            'en_attente_paiement' => 'En attente de paiement',
            'confirmee' => 'Confirmée',
            'en_preparation' => 'En préparation',
            'pret' => 'Prête à être récupérée',
            'recuperee' => 'Récupérée',
            'annulee' => 'Annulée'
        ];

        return $statuts[$this->statut ?? ''] ?? (string) $this->statut;
    }

    public function getStatutCouleur(): string
    {
        $couleurs = [
            'en_attente' => 'warning',
            'en_attente_paiement' => 'warning',
            'confirmee' => 'primary',
            'en_preparation' => 'info',
            'pret' => 'success',
            'recuperee' => 'secondary',
            'annulee' => 'danger'
        ];

        return $couleurs[$this->statut] ?? 'secondary';
    }

    /**
     * Obtenir le nombre total d'articles dans la commande
     */
    public function getNombreTotalArticles(): int
    {
        $total = 0;
        foreach ($this->lignesCommandes as $ligne) {
            $total += $ligne->getQuantite();
        }
        return $total;
    }

    /**
     * Obtenir le détail des articles pour l'affichage
     */
    public function getDetailsArticles(): array
    {
        $details = [];
        foreach ($this->lignesCommandes as $ligne) {
            $produit = $ligne->getProduit();
            $details[] = [
                'produit' => $produit?->getNom() ?? 'Produit indisponible',
                'quantite' => $ligne->getQuantite(),
                'prix_unitaire' => $ligne->getPrixUnitaire(),
                'sous_total' => $ligne->getSousTotal()
            ];
        }
        return $details;
    }
}
