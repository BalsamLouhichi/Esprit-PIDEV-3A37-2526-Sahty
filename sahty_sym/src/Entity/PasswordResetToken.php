<?php

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: 'password_reset_token')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Ignore]
    private string $token = '';

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isUsed = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = (new \DateTimeImmutable())->modify('+1 hour');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    #[Ignore]
    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(#[\SensitiveParameter] string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    public function setIsUsed(bool $isUsed): self
    {
        $this->isUsed = $isUsed;
        return $this;
    }

    public function isExpired(): bool
    {
        return new \DateTime() > $this->expiresAt;
    }

    public function isValid(): bool
    {
        return !$this->isUsed && !$this->isExpired();
    }
}
