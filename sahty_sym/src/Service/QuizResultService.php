<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\Recommandation;

class QuizResultService
{
    /**
     * @param array<int|string, int|string> $answers
     * @return array{
     *     totalScore: int,
     *     maxScore: int,
     *     categoryScores: array<string, int>,
     *     problems: list<string>,
     *     recommendations: list<Recommandation>,
     *     interpretation: string
     * }
     */
    public function calculate(Quiz $quiz, array $answers): array
    {
        $totalScore = 0;
        $categoryScores = [];
        $position = 0;

        /** @var Question $question */
        foreach ($quiz->getQuestions() as $question) {
            $position++;
            $value = $this->resolveAnswerValue($question, $answers, $position);

            if ($question->isReverse()) {
                // Reverse scoring for likert_0_4
                $value = 4 - $value;
            }

            $totalScore += $value;

            $cat = $question->getCategory();
            if ($cat) {
                $categoryScores[$cat] = ($categoryScores[$cat] ?? 0) + $value;
            }
        }

        $problemCats = [];
        foreach ($categoryScores as $cat => $score) {
            if ($score >= 10) {
                $problemCats[] = $cat;
            }
        }

        $selected = [];
        foreach ($quiz->getRecommandations() as $reco) {
            $scoreOk = $totalScore >= $reco->getMinScore() && $totalScore <= $reco->getMaxScore();

            $catMatch = true;
            if ($reco->getTargetCategories()) {
                $targets = array_map('trim', explode(',', $reco->getTargetCategories()));
                $catMatch = false;
                foreach ($targets as $t) {
                    if (in_array($t, $problemCats, true)) {
                        $catMatch = true;
                        break;
                    }
                }
            }

            if ($scoreOk && $catMatch) {
                $selected[] = $reco;
            }
        }

        usort($selected, static function (Recommandation $a, Recommandation $b): int {
            $severityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $bOrder = $severityOrder[$b->getSeverity()] ?? 1;
            $aOrder = $severityOrder[$a->getSeverity()] ?? 1;

            return $bOrder <=> $aOrder;
        });

        return [
            'totalScore' => $totalScore,
            'maxScore' => count($quiz->getQuestions()) * 4,
            'categoryScores' => $categoryScores,
            'problems' => $problemCats,
            'recommendations' => $selected,
            'interpretation' => $this->getInterpretation($totalScore),
        ];
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

    /**
     * Accept answers indexed by question id, orderInQuiz, or sequence position.
     *
     * @param array<int|string, int|string> $answers
     */
    private function resolveAnswerValue(Question $question, array $answers, int $position): int
    {
        $questionId = $question->getId();
        if ($questionId !== null) {
            if (array_key_exists($questionId, $answers)) {
                return (int) $answers[$questionId];
            }

            $questionIdKey = (string) $questionId;
            if (array_key_exists($questionIdKey, $answers)) {
                return (int) $answers[$questionIdKey];
            }
        }

        $order = $question->getOrderInQuiz();
        if (array_key_exists($order, $answers)) {
            return (int) $answers[$order];
        }

        $orderKey = (string) $order;
        if (array_key_exists($orderKey, $answers)) {
            return (int) $answers[$orderKey];
        }

        if (array_key_exists($position, $answers)) {
            return (int) $answers[$position];
        }

        $positionKey = (string) $position;
        if (array_key_exists($positionKey, $answers)) {
            return (int) $answers[$positionKey];
        }

        return 0;
    }
}
