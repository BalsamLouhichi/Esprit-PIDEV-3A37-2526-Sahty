<?php

namespace App\Entity;

use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quiz $quiz = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "Le texte de la question est obligatoire.")]
    private ?string $text = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: ['likert_0_4', 'likert_1_5', 'yes_no'], message: 'Type invalide.')]
    private ?string $type = 'likert_0_4';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null; // ex: stress, anxiete, concentration, sommeil

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $orderInQuiz = 1;

    #[ORM\Column]
    private bool $reverse = false;

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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getOrderInQuiz(): int
    {
        return $this->orderInQuiz;
    }

    public function setOrderInQuiz(int $orderInQuiz): static
    {
        $this->orderInQuiz = $orderInQuiz;
        return $this;
    }

    public function isReverse(): bool
    {
        return $this->reverse;
    }

    public function setReverse(bool $reverse): static
    {
        $this->reverse = $reverse;
        return $this;
    }
}