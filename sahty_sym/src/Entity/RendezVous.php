<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTime $dateRdv;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private \DateTime $heureRdv;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $raison = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'en attente';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $creeLe;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    #[ORM\Column(length: 20)]
    private string $typeConsultation = 'cabinet';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $meetingUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $meetingProvider = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $meetingCreatedAt;

    #[ORM\ManyToOne(inversedBy: 'rendezVous', fetch: 'LAZY')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne(fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Medecin $medecin = null;
    
    // ✅ Fiche médicale maintenant facultative
    #[ORM\OneToOne(inversedBy: 'rendezVous', cascade: ['persist', 'remove'], fetch: 'LAZY')]
    #[ORM\JoinColumn(nullable: true)]
    private ?FicheMedicale $ficheMedicale = null;

    /**
     * Constructeur - Initialise automatiquement la date de création
     */
    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->creeLe = $now;
        $this->meetingCreatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateRdv(): ?\DateTime
    {
        return isset($this->dateRdv) ? $this->dateRdv : null;
    }

    public function setDateRdv(\DateTime $dateRdv): static
    {
        $this->dateRdv = $dateRdv;

        return $this;
    }

    public function getHeureRdv(): ?\DateTime
    {
        return isset($this->heureRdv) ? $this->heureRdv : null;
    }

    public function setHeureRdv(\DateTime $heureRdv): static
    {
        $this->heureRdv = $heureRdv;

        return $this;
    }

    public function getRaison(): ?string
    {
        return $this->raison;
    }

    public function setRaison(?string $raison): static
    {
        $this->raison = $raison;

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

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTimeImmutable $creeLe): static
    {
        $this->creeLe = $creeLe;

        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;

        return $this;
    }

    public function getTypeConsultation(): string
    {
        return $this->typeConsultation;
    }

    public function setTypeConsultation(string $typeConsultation): static
    {
        $this->typeConsultation = $typeConsultation;

        return $this;
    }

    public function getMeetingUrl(): ?string
    {
        return $this->meetingUrl;
    }

    public function setMeetingUrl(?string $meetingUrl): static
    {
        $this->meetingUrl = $meetingUrl;

        return $this;
    }

    public function getMeetingProvider(): ?string
    {
        return $this->meetingProvider;
    }

    public function setMeetingProvider(?string $meetingProvider): static
    {
        $this->meetingProvider = $meetingProvider;

        return $this;
    }

    public function getMeetingCreatedAt(): \DateTimeImmutable
    {
        return $this->meetingCreatedAt;
    }

    public function setMeetingCreatedAt(\DateTimeImmutable $meetingCreatedAt): static
    {
        $this->meetingCreatedAt = $meetingCreatedAt;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getMedecin(): ?Medecin
    {
        return $this->medecin;
    }

    public function setMedecin(?Medecin $medecin): static
    {
        $this->medecin = $medecin;

        return $this;
    }

    public function getFicheMedicale(): ?FicheMedicale
    {
        return $this->ficheMedicale;
    }

    public function setFicheMedicale(?FicheMedicale $ficheMedicale): static
    {
        $this->ficheMedicale = $ficheMedicale;

        return $this;
    }
}
