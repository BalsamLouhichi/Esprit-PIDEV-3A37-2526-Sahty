<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Quiz;

class QuizAiGeneratedQuizBuilderService
{
    /**
     * @param array<string,mixed> $generatedQuiz
     */
    public function buildFromPayload(string $category, array $generatedQuiz): ?Quiz
    {
        $questions = $this->extractGeneratedQuestions($generatedQuiz['questions'] ?? null);
        if ($questions === []) {
            return null;
        }

        $quiz = new Quiz();
        $quiz->setName((string) ($generatedQuiz['name'] ?? 'Quiz IA - ' . ucfirst($category)));
        $quiz->setDescription((string) ($generatedQuiz['description'] ?? ('Quiz genere automatiquement pour la categorie ' . $category . '.')));

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setText((string) $questionData['text']);
            $question->setType((string) ($questionData['type'] ?? 'likert_0_4'));
            $question->setCategory((string) ($questionData['category'] ?? $category));
            $question->setReverse((bool) ($questionData['reverse'] ?? false));
            $question->setOrderInQuiz($index + 1);
            $quiz->addQuestion($question);
        }

        return $quiz->getQuestions()->count() > 0 ? $quiz : null;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function extractGeneratedQuestions(mixed $rawQuestions): array
    {
        if (!is_array($rawQuestions) || $rawQuestions === []) {
            return [];
        }

        return array_values(array_filter(
            $rawQuestions,
            static fn (mixed $item): bool => is_array($item) && !empty($item['text'])
        ));
    }
}
