<?php

namespace App\Entity;

use App\Repository\QuizAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
class QuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $user = null;

    #[ORM\Column(length: 20)]
    private string $status = 'in_progress';

    #[ORM\Column]
    private int $currentQuestionIndex = 0;

    #[ORM\Column]
    private int $answeredCount = 0;

    #[ORM\Column]
    private int $totalQuestions = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $score = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $answersJson = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detectedCategoriesJson = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

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

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCurrentQuestionIndex(): int
    {
        return $this->currentQuestionIndex;
    }

    public function setCurrentQuestionIndex(int $currentQuestionIndex): static
    {
        $this->currentQuestionIndex = max(0, $currentQuestionIndex);
        return $this;
    }

    public function getAnsweredCount(): int
    {
        return $this->answeredCount;
    }

    public function setAnsweredCount(int $answeredCount): static
    {
        $this->answeredCount = max(0, $answeredCount);
        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): static
    {
        $this->totalQuestions = max(0, $totalQuestions);
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getAnswersJson(): ?string
    {
        return $this->answersJson;
    }

    public function setAnswersJson(?string $answersJson): static
    {
        $this->answersJson = $answersJson;
        return $this;
    }

    public function getDetectedCategoriesJson(): ?string
    {
        return $this->detectedCategoriesJson;
    }

    public function setDetectedCategoriesJson(?string $detectedCategoriesJson): static
    {
        $this->detectedCategoriesJson = $detectedCategoriesJson;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
