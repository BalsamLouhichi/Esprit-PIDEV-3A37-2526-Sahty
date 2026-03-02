<?php

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\Question;
use App\Entity\Recommandation;

class QuizResultService
{
    public function calculate(Quiz $quiz, array $answers): array
    {
        $totalScore = 0;
        $categoryScores = [];
        $questions = $quiz->getQuestions();

        /** @var Question $question */
        foreach ($questions as $questionIndex => $question) {
            $value = $this->resolveAnswerValue($question, $answers, (int) $questionIndex);
            $totalScore += $value;
            $this->addCategoryScore($categoryScores, $question, $value);
        }

        $problemCats = $this->detectProblemCategories($categoryScores);
        $selected = $this->selectRecommendations($quiz, $totalScore, $problemCats);
        $this->sortRecommendationsBySeverity($selected);

        return [
            'totalScore' => $totalScore,
            'maxScore' => count($questions) * 4,
            'categoryScores' => $categoryScores,
            'problems' => $problemCats,
            'recommendations' => $selected,
            'interpretation' => $this->getInterpretation($totalScore),
        ];
    }

    private function resolveAnswerValue(Question $question, array $answers, int $questionIndex): int
    {
        $orderKey = $question->getOrderInQuiz();
        $questionId = $question->getId();
        $answerKey = $orderKey;

        if ($questionId !== null && array_key_exists($questionId, $answers)) {
            $answerKey = $questionId;
        } elseif (!array_key_exists($orderKey, $answers)) {
            $answerKey = $questionIndex + 1;
        }

        $value = array_key_exists($answerKey, $answers) ? (int) $answers[$answerKey] : 0;
        return $question->isReverse() ? 4 - $value : $value;
    }

    private function addCategoryScore(array &$categoryScores, Question $question, int $value): void
    {
        $category = $question->getCategory();
        if (!$category) {
            return;
        }

        $categoryScores[$category] = ($categoryScores[$category] ?? 0) + $value;
    }

    /**
     * @param array<string,int> $categoryScores
     * @return string[]
     */
    private function detectProblemCategories(array $categoryScores): array
    {
        $problemCats = [];

        foreach ($categoryScores as $cat => $score) {
            if ($score >= 10) {
                $problemCats[] = $cat;
            }
        }

        return $problemCats;
    }

    /**
     * @param string[] $problemCats
     * @return Recommandation[]
     */
    private function selectRecommendations(Quiz $quiz, int $totalScore, array $problemCats): array
    {
        $selected = [];

        /** @var Recommandation $reco */
        foreach ($quiz->getRecommandations() as $reco) {
            $scoreOk = $totalScore >= $reco->getMinScore() && $totalScore <= $reco->getMaxScore();
            $categoryOk = $this->recommendationMatchesProblemCategories($reco, $problemCats);

            if ($scoreOk && $categoryOk) {
                $selected[] = $reco;
            }
        }

        return $selected;
    }

    /**
     * @param string[] $problemCats
     */
    private function recommendationMatchesProblemCategories(Recommandation $reco, array $problemCats): bool
    {
        $targetCategories = $reco->getTargetCategories();
        if (!$targetCategories) {
            return true;
        }

        $targets = array_map('trim', explode(',', $targetCategories));
        foreach ($targets as $target) {
            if (in_array($target, $problemCats, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Recommandation[] $recommendations
     */
    private function sortRecommendationsBySeverity(array &$recommendations): void
    {
        $severityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];

        usort(
            $recommendations,
            static fn (Recommandation $a, Recommandation $b): int =>
                ($severityOrder[$b->getSeverity()] ?? 1) <=> ($severityOrder[$a->getSeverity()] ?? 1)
        );
    }

    private function getInterpretation(int $score): string
    {
        if ($score <= 14) {
            return 'Votre score est faible. Continuez vos bonnes habitudes !';
        }

        if ($score <= 24) {
            return 'Score modere. Quelques ajustements peuvent ameliorer votre bien-etre.';
        }

        return 'Score eleve. Il est conseille de consulter un professionnel si les symptomes persistent.';
    }
}
