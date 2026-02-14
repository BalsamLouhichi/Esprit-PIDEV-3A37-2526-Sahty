<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\UniqueConstraint(name: 'UNIQ_EMAIL', fields: ['email'])]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'discr', type: 'string')]
#[ORM\DiscriminatorMap([
    'admin' => Administrateur::class,
    'medecin' => Medecin::class,
    'patient' => Patient::class,
    'responsable_labo' => ResponsableLaboratoire::class,
    'responsable_para' => ResponsableParapharmacie::class,
])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    // ============ CONSTANTES POUR LES RÔLES ============
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_MEDECIN = 'ROLE_MEDECIN';
    public const ROLE_PATIENT = 'ROLE_PATIENT';
    public const ROLE_RESPONSABLE_LABO = 'ROLE_RESPONSABLE_LABO';
    public const ROLE_RESPONSABLE_PARA = 'ROLE_RESPONSABLE_PARA';
    
    public const ROLE_SIMPLE_ADMIN = 'admin';
    public const ROLE_SIMPLE_MEDECIN = 'medecin';
    public const ROLE_SIMPLE_PATIENT = 'patient';
    public const ROLE_SIMPLE_RESPONSABLE_LABO = 'responsable_labo';
    public const ROLE_SIMPLE_RESPONSABLE_PARA = 'responsable_para';

    // ============ PROPRIÉTÉS ============
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    protected ?string $email = null;

    #[ORM\Column(name: 'password', length: 255)]
    protected ?string $password = null;

    #[ORM\Column(length: 30)]
    protected ?string $role = null;

    #[ORM\Column(length: 100)]
    protected ?string $nom = null;

    #[ORM\Column(length: 100)]
    protected ?string $prenom = null;

    #[ORM\Column(length: 20, nullable: true)]
    protected ?string $telephone = null;

    #[ORM\Column(type: 'date', nullable: true)]
    protected ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    protected bool $estActif = true;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $photoProfil = null;

    #[ORM\Column(name: 'cree_le', type: 'datetime')]
    protected \DateTime $creeLe;

    // ============ CONSTRUCTEUR ============
    public function __construct()
    {
        $this->creeLe = new \DateTime();
    }

    // ============ MÉTHODES DE SÉCURITÉ (UserInterface) ============
    
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getUsername(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return [$this->getRoleSymfony()];
    }

    /**
     * Récupère le rôle au format Symfony (ROLE_XXX)
     */
    public function getRoleSymfony(): string
    {
        return match($this->role) {
            self::ROLE_SIMPLE_ADMIN => self::ROLE_ADMIN,
            self::ROLE_SIMPLE_MEDECIN => self::ROLE_MEDECIN,
            self::ROLE_SIMPLE_PATIENT => self::ROLE_PATIENT,
            self::ROLE_SIMPLE_RESPONSABLE_LABO => self::ROLE_RESPONSABLE_LABO,
            self::ROLE_SIMPLE_RESPONSABLE_PARA => self::ROLE_RESPONSABLE_PARA,
            default => 'ROLE_USER',
        };
    }

    /**
     * Vérifie si l'utilisateur a un rôle simple spécifique
     */
    public function hasRole(string $roleSimple): bool
    {
        return $this->role === $roleSimple;
    }

    /**
     * Vérifie si l'utilisateur a un rôle Symfony spécifique
     */
    public function hasRoleSymfony(string $roleSymfony): bool
    {
        return $this->getRoleSymfony() === $roleSymfony;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void {}

    // ============ GETTERS / SETTERS BASIQUES ============
    
    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getEmail(): ?string 
    { 
        return $this->email; 
    }

    public function setEmail(string $email): self 
    { 
        $this->email = $email; 
        return $this; 
    }

    public function setPassword(string $password): self 
    { 
        $this->password = $password; 
        return $this; 
    }

    public function getRole(): ?string 
    { 
        return $this->role; 
    }
    
    public function setRole(string $role): self 
    { 
        $validRoles = [
            self::ROLE_SIMPLE_ADMIN,
            self::ROLE_SIMPLE_MEDECIN,
            self::ROLE_SIMPLE_PATIENT,
            self::ROLE_SIMPLE_RESPONSABLE_LABO,
            self::ROLE_SIMPLE_RESPONSABLE_PARA,
        ];
        
        if (!in_array($role, $validRoles)) {
            throw new \InvalidArgumentException(sprintf('Rôle "%s" invalide', $role));
        }
        
        $this->role = $role; 
        return $this; 
    }

    public function getNom(): ?string 
    { 
        return $this->nom; 
    }

    public function setNom(string $nom): self 
    { 
        $this->nom = $nom; 
        return $this; 
    }

    public function getPrenom(): ?string 
    { 
        return $this->prenom; 
    }

    public function setPrenom(string $prenom): self 
    { 
        $this->prenom = $prenom; 
        return $this; 
    }

    public function getTelephone(): ?string 
    { 
        return $this->telephone; 
    }

    public function setTelephone(?string $telephone): self 
    { 
        $this->telephone = $telephone; 
        return $this; 
    }

    public function getDateNaissance(): ?\DateTimeInterface 
    { 
        return $this->dateNaissance; 
    }

    public function setDateNaissance(?\DateTimeInterface $date): self 
    { 
        $this->dateNaissance = $date; 
        return $this; 
    }

    public function isEstActif(): bool 
    { 
        return $this->estActif; 
    }

    public function setEstActif(bool $actif): self 
    { 
        $this->estActif = $actif; 
        return $this; 
    }

    public function getPhotoProfil(): ?string 
    { 
        return $this->photoProfil; 
    }

    public function setPhotoProfil(?string $photoProfil): self 
    { 
        $this->photoProfil = $photoProfil; 
        return $this; 
    }

    public function getCreeLe(): \DateTime 
    { 
        return $this->creeLe; 
    }

    public function setCreeLe(\DateTime $creeLe): self 
    { 
        $this->creeLe = $creeLe; 
        return $this; 
    }

    // ============ MÉTHODES POUR DÉFINIR DES RÔLES SPÉCIFIQUES ============
    
    public function setAdminRole(): self
    {
        $this->role = self::ROLE_SIMPLE_ADMIN;
        return $this;
    }

    public function setMedecinRole(): self
    {
        $this->role = self::ROLE_SIMPLE_MEDECIN;
        return $this;
    }

    public function setPatientRole(): self
    {
        $this->role = self::ROLE_SIMPLE_PATIENT;
        return $this;
    }

    public function setResponsableLaboRole(): self
    {
        $this->role = self::ROLE_SIMPLE_RESPONSABLE_LABO;
        return $this;
    }

    public function setResponsableParaRole(): self
    {
        $this->role = self::ROLE_SIMPLE_RESPONSABLE_PARA;
        return $this;
    }

    // ============ MÉTHODES POUR VÉRIFIER DES RÔLES SPÉCIFIQUES ============
    
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SIMPLE_ADMIN);
    }

    public function isMedecin(): bool
    {
        return $this->hasRole(self::ROLE_SIMPLE_MEDECIN);
    }

    public function isPatient(): bool
    {
        return $this->hasRole(self::ROLE_SIMPLE_PATIENT);
    }

    public function isResponsableLabo(): bool
    {
        return $this->hasRole(self::ROLE_SIMPLE_RESPONSABLE_LABO);
    }

    public function isResponsablePara(): bool
    {
        return $this->hasRole(self::ROLE_SIMPLE_RESPONSABLE_PARA);
    }

    // ============ MÉTHODES UTILITAIRES ============
    
    /**
     * Obtenir le nom complet
     */
    public function getNomComplet(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Calculer l'âge à partir de la date de naissance
     */
    public function getAge(): ?int
    {
        if (!$this->dateNaissance) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->dateNaissance);
        return $interval->y;
    }
}
