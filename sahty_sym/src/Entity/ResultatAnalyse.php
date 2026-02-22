<?php

namespace App\Entity;

use App\Repository\ResultatAnalyseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultatAnalyseRepository::class)]
#[ORM\Table(name: 'resultat_analyse')]
class ResultatAnalyse
{
    public const AI_STATUS_PENDING = 'pending';
    public const AI_STATUS_DONE = 'done';
    public const AI_STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'resultatAnalyse', targetEntity: DemandeAnalyse::class)]
    #[ORM\JoinColumn(name: 'demande_analyse_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE', unique: true)]
    private ?DemandeAnalyse $demandeAnalyse = null;

    #[ORM\Column(name: 'source_pdf', length: 255, nullable: true)]
    private ?string $sourcePdf = null;

    #[ORM\Column(name: 'ai_status', length: 20)]
    private string $aiStatus = self::AI_STATUS_PENDING;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $anomalies = null;

    #[ORM\Column(name: 'danger_score', type: 'integer', nullable: true)]
    private ?int $dangerScore = null;

    #[ORM\Column(name: 'danger_level', length: 20, nullable: true)]
    private ?string $dangerLevel = null;

    #[ORM\Column(name: 'resume_bilan', type: 'text', nullable: true)]
    private ?string $resumeBilan = null;

    #[ORM\Column(name: 'modele_version', length: 100, nullable: true)]
    private ?string $modeleVersion = null;

    #[ORM\Column(name: 'ai_raw_response', type: 'json', nullable: true)]
    private ?array $aiRawResponse = null;

    #[ORM\Column(name: 'analyse_le', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $analyseLe = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemandeAnalyse(): ?DemandeAnalyse
    {
        return $this->demandeAnalyse;
    }

    public function setDemandeAnalyse(DemandeAnalyse $demandeAnalyse): self
    {
        $this->demandeAnalyse = $demandeAnalyse;
        if ($demandeAnalyse->getResultatAnalyse() !== $this) {
            $demandeAnalyse->setResultatAnalyse($this);
        }

        return $this;
    }

    public function getSourcePdf(): ?string
    {
        return $this->sourcePdf;
    }

    public function setSourcePdf(?string $sourcePdf): self
    {
        $this->sourcePdf = $sourcePdf;
        return $this;
    }

    public function getAiStatus(): string
    {
        return $this->aiStatus;
    }

    public function setAiStatus(string $aiStatus): self
    {
        $this->aiStatus = $aiStatus;
        return $this;
    }

    public function getAnomalies(): ?array
    {
        return $this->anomalies;
    }

    public function setAnomalies(?array $anomalies): self
    {
        $this->anomalies = $anomalies;
        return $this;
    }

    public function getDangerScore(): ?int
    {
        return $this->dangerScore;
    }

    public function setDangerScore(?int $dangerScore): self
    {
        $this->dangerScore = $dangerScore;
        return $this;
    }

    public function getDangerLevel(): ?string
    {
        return $this->dangerLevel;
    }

    public function setDangerLevel(?string $dangerLevel): self
    {
        $this->dangerLevel = $dangerLevel;
        return $this;
    }

    public function getResumeBilan(): ?string
    {
        return $this->resumeBilan;
    }

    public function setResumeBilan(?string $resumeBilan): self
    {
        $this->resumeBilan = $resumeBilan;
        return $this;
    }

    public function getModeleVersion(): ?string
    {
        return $this->modeleVersion;
    }

    public function setModeleVersion(?string $modeleVersion): self
    {
        $this->modeleVersion = $modeleVersion;
        return $this;
    }

    public function getAiRawResponse(): ?array
    {
        return $this->aiRawResponse;
    }

    public function setAiRawResponse(?array $aiRawResponse): self
    {
        $this->aiRawResponse = $aiRawResponse;
        return $this;
    }

    public function getAnalyseLe(): ?\DateTimeInterface
    {
        return $this->analyseLe;
    }

    public function setAnalyseLe(?\DateTimeInterface $analyseLe): self
    {
        $this->analyseLe = $analyseLe;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }
}
