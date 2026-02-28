<?php

namespace App\Entity;

use App\Repository\RecommandationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecommandationRepository::class)]
class Recommandation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'recommandations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le quiz est obligatoire.")]
    private ?Quiz $quiz = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(min: 3, max: 255)]
    private ?string $title = null; // titre court pour l'affichage

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $tips = null; // • conseil 1\n• conseil 2

    #[ORM\Column]
    #[Assert\NotBlank]
    private ?int $min_score = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    private ?int $max_score = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $type_probleme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $target_categories = null; // "stress,concentration" séparé par virgule

    #[ORM\Column(length: 20, options: ["default" => "medium"])]
    private string $severity = 'medium'; // low / medium / high

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $video_url = null; // URL de la vidéo YouTube

    public function getVideoUrl(): ?string
    {
        return $this->video_url;
    }

    public function setVideoUrl(?string $video_url): static
    {
        $this->video_url = $video_url;
        return $this;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Tous les getters & setters (je mets seulement les nouveaux + importants)

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getTips(): ?string
    {
        return $this->tips;
    }

    public function setTips(?string $tips): static
    {
        $this->tips = $tips;
        return $this;
    }

    public function getMinScore(): ?int
    {
        return $this->min_score;
    }

    public function setMinScore(int $min_score): static
    {
        $this->min_score = $min_score;
        return $this;
    }

    public function getMaxScore(): ?int
    {
        return $this->max_score;
    }

    public function setMaxScore(int $max_score): static
    {
        $this->max_score = $max_score;
        return $this;
    }

    public function getTypeProbleme(): ?string
    {
        return $this->type_probleme;
    }

    public function setTypeProbleme(?string $type_probleme): static
    {
        $this->type_probleme = $type_probleme;
        return $this;
    }

    public function getTargetCategories(): ?string
    {
        return $this->target_categories;
    }

    public function setTargetCategories(?string $target_categories): static
    {
        $this->target_categories = $target_categories;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}