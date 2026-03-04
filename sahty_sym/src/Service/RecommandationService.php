<?php

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\Recommandation;

class RecommandationService
{
    private const DEFAULT_VIDEO_URL = 'https://www.youtube.com/embed/hnpQrMqDoqE';

    private const CATEGORY_VIDEO_MAP = [
        'stress' => 'https://www.youtube.com/embed/hnpQrMqDoqE',
        'anxiete' => 'https://www.youtube.com/embed/inpok4MKVLM',
        'sommeil' => 'https://www.youtube.com/embed/aEqlQvczMJQ',
        'alimentation' => 'https://www.youtube.com/embed/fqhYBTg73fw',
        'nutrition' => 'https://www.youtube.com/embed/fqhYBTg73fw',
        'activite' => 'https://www.youtube.com/embed/ml6cT4AZdqI',
        'sport' => 'https://www.youtube.com/embed/ml6cT4AZdqI',
        'humeur' => 'https://www.youtube.com/embed/ZToicYcHIOU',
        'concentration' => 'https://www.youtube.com/embed/W19PdslW7iw',
        'energie' => 'https://www.youtube.com/embed/50kH47ZztHs',
        'bien_etre' => 'https://www.youtube.com/embed/hnpQrMqDoqE',
        'bien-etre' => 'https://www.youtube.com/embed/hnpQrMqDoqE',
        'bienetre' => 'https://www.youtube.com/embed/hnpQrMqDoqE',
    ];

    /**
     * Get recommendations filtered by score and categories
     *
     * @param Quiz $quiz
     * @param int $score
     * @param array<int, string> $problems Array of problematic categories
     * @return Recommandation[]
     */
    public function getFiltered(Quiz $quiz, int $score, array $problems = []): array
    {
        $selected = [];

        foreach ($quiz->getRecommandations() as $reco) {
            if (!$this->matchesScore($score, $reco)) {
                continue;
            }

            if (!$this->matchesCategory($problems, $reco)) {
                continue;
            }

            $selected[] = $reco;
        }

        return $this->sortBySeverity($selected);
    }

    /**
     * Resolve the video URL to display for a recommendation.
     * Priority: explicit URL from admin > category fallback map.
     */
    public function resolveVideoUrl(Recommandation $recommandation): ?string
    {
        $manualUrl = $this->normalizeYoutubeUrl($recommandation->getVideoUrl());
        if ($manualUrl) {
            return $manualUrl;
        }

        $categories = [];

        $targetCategories = $recommandation->getTargetCategories();
        if ($targetCategories) {
            $categories = array_merge($categories, array_map('trim', explode(',', $targetCategories)));
        }

        $typeProbleme = $recommandation->getTypeProbleme();
        if ($typeProbleme) {
            $categories[] = $typeProbleme;
        }

        foreach ($categories as $category) {
            $normalized = $this->normalizeCategory($category);
            if (isset(self::CATEGORY_VIDEO_MAP[$normalized])) {
                return self::CATEGORY_VIDEO_MAP[$normalized];
            }
        }

        return self::DEFAULT_VIDEO_URL;
    }

    /**
     * Check if score falls within recommendation range
     */
    private function matchesScore(int $score, Recommandation $reco): bool
    {
        $minScore = $reco->getMinScore();
        $maxScore = $reco->getMaxScore();

        // Legacy/default entity state: 0/0 means "no score constraint configured yet".
        if ($minScore === 0 && $maxScore === 0) {
            return true;
        }

        return $score >= $minScore && $score <= $maxScore;
    }

    /**
     * Check if problematic categories match recommendation targets
     */
    /**
     * @param array<int, string> $problems
     */
    private function matchesCategory(array $problems, Recommandation $reco): bool
    {
        $targets = $reco->getTargetCategories();

        if (!$targets) {
            return true;
        }

        if (empty($problems)) {
            return false;
        }

        $targetArray = array_map('trim', explode(',', $targets));

        foreach ($targetArray as $target) {
            if (in_array($target, $problems, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort recommendations by severity: high > medium > low
     */
    /**
     * @param array<int, Recommandation> $recommendations
     * @return array<int, Recommandation>
     */
    private function sortBySeverity(array $recommendations): array
    {
        usort($recommendations, function (Recommandation $a, Recommandation $b) {
            $severityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];

            $aOrder = $severityOrder[$a->getSeverity()] ?? 1;
            $bOrder = $severityOrder[$b->getSeverity()] ?? 1;

            return $bOrder <=> $aOrder;
        });

        return $recommendations;
    }

    /**
     * Get all recommendations for a quiz, grouped by severity
     */
    /**
     * @return array{high: array<int, Recommandation>, medium: array<int, Recommandation>, low: array<int, Recommandation>}
     */
    public function getGroupedBySeverity(Quiz $quiz): array
    {
        $grouped = [
            'high' => [],
            'medium' => [],
            'low' => [],
        ];

        foreach ($quiz->getRecommandations() as $reco) {
            $severity = $reco->getSeverity();
            if (isset($grouped[$severity])) {
                $grouped[$severity][] = $reco;
            }
        }

        return $grouped;
    }

    /**
     * Get urgent recommendations (high severity)
     */
    /**
     * @return array<int, Recommandation>
     */
    public function getUrgent(Quiz $quiz): array
    {
        return array_filter(
            $quiz->getRecommandations()->toArray(),
            fn (Recommandation $r) => $r->getSeverity() === 'high'
        );
    }

    /**
     * Count recommendations by severity for a quiz
     */
    /**
     * @return array{high:int,medium:int,low:int}
     */
    public function countBySeverity(Quiz $quiz): array
    {
        return [
            'high' => count(array_filter(
                $quiz->getRecommandations()->toArray(),
                fn (Recommandation $r) => $r->getSeverity() === 'high'
            )),
            'medium' => count(array_filter(
                $quiz->getRecommandations()->toArray(),
                fn (Recommandation $r) => $r->getSeverity() === 'medium'
            )),
            'low' => count(array_filter(
                $quiz->getRecommandations()->toArray(),
                fn (Recommandation $r) => $r->getSeverity() === 'low'
            )),
        ];
    }

    private function normalizeCategory(string $value): string
    {
        $value = strtolower(trim($value));

        if (str_contains($value, 'stress')) {
            return 'stress';
        }
        if (str_contains($value, 'anx')) {
            return 'anxiete';
        }
        if (str_contains($value, 'sommeil')) {
            return 'sommeil';
        }
        if (str_contains($value, 'aliment') || str_contains($value, 'nutri')) {
            return 'alimentation';
        }
        if (str_contains($value, 'activ') || str_contains($value, 'sport')) {
            return 'activite';
        }
        if (str_contains($value, 'humeur') || str_contains($value, 'emotion')) {
            return 'humeur';
        }
        if (str_contains($value, 'concentr')) {
            return 'concentration';
        }
        if (str_contains($value, 'energie') || str_contains($value, 'fatigue')) {
            return 'energie';
        }
        if (str_contains($value, 'bien_etre') || str_contains($value, 'bien-etre') || str_contains($value, 'bienetre') || str_contains($value, 'bien etre')) {
            return 'bien_etre';
        }

        return $value;
    }

    private function normalizeYoutubeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = trim($url);

        if (str_contains($url, '/embed/')) {
            return $url;
        }

        $patterns = [
            '#youtube\.com/watch\?v=([a-zA-Z0-9_-]{11})#',
            '#youtu\.be/([a-zA-Z0-9_-]{11})#',
            '#youtube\.com/shorts/([a-zA-Z0-9_-]{11})#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return 'https://www.youtube.com/embed/' . $matches[1];
            }
        }

        return $url;
    }
}
