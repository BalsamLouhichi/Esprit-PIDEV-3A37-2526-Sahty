<?php
// src/Entity/ResponsableParapharmacie.php

namespace App\Entity;

use App\Repository\ResponsableParapharmacieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResponsableParapharmacieRepository::class)]
#[ORM\Table(name: 'responsable_parapharmacie')]
class ResponsableParapharmacie extends Utilisateur
{
    /**
     * Relation avec l'entité Parapharmacie
     * Un responsable peut gérer une seule parapharmacie
     */
    #[ORM\ManyToOne(targetEntity: Parapharmacie::class, inversedBy: 'responsables')]
    #[ORM\JoinColumn(name: 'parapharmacie_id', referencedColumnName: 'id', nullable: true)]
    private ?Parapharmacie $parapharmacie = null;

    /**
     * Flag pour détecter la première connexion
     * Permet de rediriger vers la page de configuration initiale
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $premiereConnexion = true;

    /**
     * Date de dernière connexion
     * Pour le suivi des activités
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $derniereConnexion = null;

    /**
     * Token pour l'invitation à rejoindre une parapharmacie
     * Utilisé lors du processus d'invitation
     */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $invitationToken = null;

    /**
     * Date d'expiration du token d'invitation
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $invitationExpireLe = null;

    /**
     * Constructeur
     */
    public function __construct()
    {
        parent::__construct();
        $this->setRole(self::ROLE_SIMPLE_RESPONSABLE_PARA);
        $this->premiereConnexion = true;
    }

    /**
     * Get the parapharmacie associated with this responsable
     */
    public function getParapharmacie(): ?Parapharmacie
    {
        return $this->parapharmacie;
    }

    /**
     * Set the parapharmacie for this responsable
     */
    public function setParapharmacie(?Parapharmacie $parapharmacie): self
    {
        $this->parapharmacie = $parapharmacie;
        return $this;
    }

    /**
     * Check if the responsable has a parapharmacie configured
     */
    public function hasParapharmacie(): bool
    {
        return $this->parapharmacie !== null;
    }

    /**
     * Get the parapharmacie ID (convenience method)
     */
    public function getParapharmacieId(): ?int
    {
        return $this->parapharmacie?->getId();
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use setParapharmacie() instead
     */
    public function setParapharmacieId(?int $parapharmacieId): self
    {
        return $this;
    }

    /**
     * Check if this is the user's first connection
     */
    public function isPremiereConnexion(): bool
    {
        return $this->premiereConnexion;
    }

    /**
     * Set the first connection flag
     */
    public function setPremiereConnexion(bool $premiereConnexion): self
    {
        $this->premiereConnexion = $premiereConnexion;
        return $this;
    }

    /**
     * Get the last connection date
     */
    public function getDerniereConnexion(): ?\DateTimeInterface
    {
        return $this->derniereConnexion;
    }

    /**
     * Set the last connection date
     */
    public function setDerniereConnexion(?\DateTimeInterface $derniereConnexion): self
    {
        $this->derniereConnexion = $derniereConnexion;
        return $this;
    }

    /**
     * Update the last connection date to now
     */
    public function updateDerniereConnexion(): self
    {
        $this->derniereConnexion = new \DateTime();
        return $this;
    }

    /**
     * Get the invitation token
     */
    public function getInvitationToken(): ?string
    {
        return $this->invitationToken;
    }

    /**
     * Set the invitation token
     */
    public function setInvitationToken(?string $invitationToken): self
    {
        $this->invitationToken = $invitationToken;
        return $this;
    }

    /**
     * Get the invitation expiration date
     */
    public function getInvitationExpireLe(): ?\DateTimeInterface
    {
        return $this->invitationExpireLe;
    }

    /**
     * Set the invitation expiration date
     */
    public function setInvitationExpireLe(?\DateTimeInterface $invitationExpireLe): self
    {
        $this->invitationExpireLe = $invitationExpireLe;
        return $this;
    }

    /**
     * Generate a new invitation token
     */
    public function genererInvitationToken(int $expireEnHeures = 48): self
    {
        $this->invitationToken = bin2hex(random_bytes(32));
        
        $expiration = new \DateTime();
        $expiration->modify('+' . $expireEnHeures . ' hours');
        $this->invitationExpireLe = $expiration;
        
        return $this;
    }

    /**
     * Check if the invitation token is valid
     */
    public function isInvitationTokenValid(?string $token): bool
    {
        if ($token === null || $this->invitationToken === null) {
            return false;
        }
        
        if ($this->invitationExpireLe === null) {
            return false;
        }
        
        return hash_equals($this->invitationToken, $token) 
            && $this->invitationExpireLe > new \DateTime();
    }

    /**
     * Clear the invitation token (after use)
     */
    public function clearInvitationToken(): self
    {
        $this->invitationToken = null;
        $this->invitationExpireLe = null;
        return $this;
    }

    /**
     * Check if the responsable can manage products
     * Always true for responsable parapharmacie
     */
    public function canManageProducts(): bool
    {
        return $this->hasParapharmacie();
    }

    /**
     * Check if the responsable can view orders
     * Always true for responsable parapharmacie
     */
    public function canViewOrders(): bool
    {
        return $this->hasParapharmacie();
    }

    /**
     * Get the nombre de produits en stock (example method)
     * This would typically query the repository
     */
    public function getNombreProduits(): int
    {
        if (!$this->hasParapharmacie()) {
            return 0;
        }
        
        // This would normally be implemented via a repository method
        // For now, return 0 as placeholder
        return 0;
    }

    /**
     * Get the nombre de commandes en attente (example method)
     * This would typically query the repository
     */
    public function getNombreCommandesEnAttente(): int
    {
        if (!$this->hasParapharmacie()) {
            return 0;
        }
        
        // This would normally be implemented via a repository method
        // For now, return 0 as placeholder
        return 0;
    }

    /**
     * String representation of the entity
     */
    public function __toString(): string
    {
        return $this->getNomComplet() . ' (' . ($this->parapharmacie?->getNom() ?? 'Parapharmacie non configurée') . ')';
    }

    /**
     * Check if the responsable needs to configure their parapharmacie
     */
    public function needsConfiguration(): bool
    {
        return $this->premiereConnexion || !$this->hasParapharmacie();
    }

    /**
     * Mark the configuration as complete
     */
    public function markConfigurationComplete(): self
    {
        $this->premiereConnexion = false;
        return $this;
    }
}