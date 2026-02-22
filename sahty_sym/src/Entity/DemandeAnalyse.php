<?php

namespace App\Entity;

use App\Repository\DemandeAnalyseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeAnalyseRepository::class)]
#[ORM\Table(name: "demande_analyse")]
class DemandeAnalyse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'demandeAnalyses')]
    #[ORM\JoinColumn(name: 'patient_id', nullable: false)]
    private ?Patient $patient = null; // Changé de patient_id à patient

    #[ORM\ManyToOne(targetEntity: Medecin::class, inversedBy: 'demandeAnalyses')]
    #[ORM\JoinColumn(name: 'medecin_id', nullable: true)] // ← Changer à true
    private ?Medecin $medecin = null;

    #[ORM\ManyToOne(targetEntity: Laboratoire::class, inversedBy: 'demandeAnalyses')]
    #[ORM\JoinColumn(name: 'laboratoire_id', nullable: false)]
    private ?Laboratoire $laboratoire = null; // Changé de laboratoire_id à laboratoire

    #[ORM\Column(length: 255)]
    private ?string $type_bilan = null;

    // Valeur par defaut basee sur la presence du PDF resultat
    #[ORM\Column(length: 50)]
    private string $statut = 'en_attente';

    // Initialisation automatique de la date
    #[ORM\Column]
    private \DateTimeImmutable $date_demande;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $programme_le = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $envoye_le = null;

    // Notes facultatives
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    // Priorité de la demande
    #[ORM\Column(length: 20)]
    private string $priorite = 'Normale'; // Ajout de la priorité

    // Liste des analyses demandées (peut être JSON ou relation OneToMany)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $analyses = [];

    #[ORM\Column(name: 'resultat_pdf', length: 255, nullable: true)]
    private ?string $resultatPdf = null;

    #[ORM\OneToOne(mappedBy: 'demandeAnalyse', targetEntity: ResultatAnalyse::class, cascade: ['persist', 'remove'])]
    private ?ResultatAnalyse $resultatAnalyse = null;

    // Constructeur pour initialiser date_demande
    public function __construct()
    {
        $this->date_demande = new \DateTimeImmutable();
        $this->analyses = [];
    }

    // --- Getters et setters avec noms corrects ---
    public function getId(): ?int
    {
        return $this->id;
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

    public function getLaboratoire(): ?Laboratoire
    {
        return $this->laboratoire;
    }

    public function setLaboratoire(?Laboratoire $laboratoire): static
    {
        $this->laboratoire = $laboratoire;
        return $this;
    }

    public function getTypeBilan(): ?string
    {
        return $this->type_bilan;
    }

    public function setTypeBilan(string $type_bilan): static
    {
        $this->type_bilan = $type_bilan;
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

    public function getDateDemande(): \DateTimeImmutable
    {
        return $this->date_demande;
    }

    public function setDateDemande(\DateTimeImmutable $date_demande): static
    {
        $this->date_demande = $date_demande;
        return $this;
    }

    public function getProgrammeLe(): ?\DateTimeInterface
    {
        return $this->programme_le;
    }

    public function setProgrammeLe(?\DateTimeInterface $programme_le): static
    {
        $this->programme_le = $programme_le;
        return $this;
    }

    public function getEnvoyeLe(): ?\DateTimeInterface
    {
        return $this->envoye_le;
    }

    public function setEnvoyeLe(?\DateTimeInterface $envoye_le): static
    {
        $this->envoye_le = $envoye_le;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getPriorite(): string
    {
        return $this->priorite;
    }

    public function setPriorite(string $priorite): static
    {
        $this->priorite = $priorite;
        return $this;
    }

    public function getAnalyses(): ?array
    {
        return $this->analyses;
    }

    public function setAnalyses(?array $analyses): static
    {
        $this->analyses = $analyses;
        return $this;
    }

    public function getResultatPdf(): ?string
    {
        return $this->resultatPdf;
    }

    public function setResultatPdf(?string $resultatPdf): static
    {
        $this->resultatPdf = $resultatPdf;
        return $this;
    }

    public function getResultatAnalyse(): ?ResultatAnalyse
    {
        return $this->resultatAnalyse;
    }

    public function setResultatAnalyse(?ResultatAnalyse $resultatAnalyse): static
    {
        $this->resultatAnalyse = $resultatAnalyse;

        if ($resultatAnalyse !== null && $resultatAnalyse->getDemandeAnalyse() !== $this) {
            $resultatAnalyse->setDemandeAnalyse($this);
        }

        return $this;
    }

    // Méthodes pratiques
    public function addAnalyse(string $analyse): static
    {
        if (!in_array($analyse, $this->analyses)) {
            $this->analyses[] = $analyse;
        }
        return $this;
    }

    public function removeAnalyse(string $analyse): static
    {
        if (($key = array_search($analyse, $this->analyses)) !== false) {
            unset($this->analyses[$key]);
            $this->analyses = array_values($this->analyses); // Réindexer
        }
        return $this;
    }

    public function getNbAnalyses(): int
    {
        return count($this->analyses);
    }

    // Pour faciliter l'affichage dans le template
    public function __toString(): string
    {
        return sprintf('Demande #%d - %s', $this->id, $this->type_bilan);
    }
}
