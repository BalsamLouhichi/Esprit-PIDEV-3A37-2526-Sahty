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

        /** @var Question $question */
        foreach ($quiz->getQuestions() as $question) {
            $qId = $question->getId();
            $value = (int) ($answers[$qId] ?? 0);

            if ($question->isReverse()) {
                // likert_0_4 inverse
                $value = 4 - $value;
            }

            $totalScore += $value;

            $cat = $question->getCategory();
            if ($cat) {
                $categoryScores[$cat] = ($categoryScores[$cat] ?? 0) + $value;
            }
        }

        // Catégories problématiques (seuil arbitraire, à ajuster)
        $problemCats = [];
        foreach ($categoryScores as $cat => $score) {
            if ($score >= 10) { // exemple : 10+ sur 20 max par catégorie
                $problemCats[] = $cat;
            }
        }

        // Filtrer recommandations
        $selected = [];
        foreach ($quiz->getRecommandations() as $reco) {
            $scoreOk = $totalScore >= $reco->getMinScore() && $totalScore <= $reco->getMaxScore();

            $catMatch = true;
            if ($reco->getTargetCategories()) {
                $targets = array_map('trim', explode(',', $reco->getTargetCategories()));
                $catMatch = false;
                foreach ($targets as $t) {
                    if (in_array($t, $problemCats)) {
                        $catMatch = true;
                        break;
                    }
                }
            }

            if ($scoreOk && $catMatch) {
                $selected[] = $reco;
            }
        }

        // Trier par gravité descendante
        usort($selected, fn($a, $b) => 
            ['high' => 3, 'medium' => 2, 'low' => 1][$b->getSeverity() ?? 'low'] 
            <=> 
            ['high' => 3, 'medium' => 2, 'low' => 1][$a->getSeverity() ?? 'low']
        );

        return [
            'totalScore'      => $totalScore,
            'maxScore'        => count($quiz->getQuestions()) * 4,
            'categoryScores'  => $categoryScores,
            'problems'        => $problemCats,
            'recommendations' => $selected,
            'interpretation'  => $this->getInterpretation($totalScore),
        ];
    }

    private function getInterpretation(int $score): string
    {
        if ($score <= 14) return "Votre score est faible. Continuez vos bonnes habitudes !";
        if ($score <= 24) return "Score modéré. Quelques ajustements peuvent améliorer votre bien-être.";
        return "Score élevé. Il est conseillé de consulter un professionnel si les symptômes persistent.";
    }
}