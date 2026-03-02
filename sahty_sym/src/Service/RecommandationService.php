<?php

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\Recommandation;

class RecommandationService
{
    private const DEFAULT_VIDEO_URL = 'https://www.youtube.com/embed/hnpQrMqDoqE';
    private const VIDEO_ANXIETE_URL = 'https://www.youtube.com/embed/inpok4MKVLM';
    private const VIDEO_SOMMEIL_URL = 'https://www.youtube.com/embed/aEqlQvczMJQ';
    private const VIDEO_ALIMENTATION_URL = 'https://www.youtube.com/embed/fqhYBTg73fw';
    private const VIDEO_ACTIVITE_URL = 'https://www.youtube.com/embed/ml6cT4AZdqI';
    private const VIDEO_HUMEUR_URL = 'https://www.youtube.com/embed/ZToicYcHIOU';
    private const VIDEO_CONCENTRATION_URL = 'https://www.youtube.com/embed/W19PdslW7iw';
    private const VIDEO_ENERGIE_URL = 'https://www.youtube.com/embed/50kH47ZztHs';

    /**
     * @var array<string, string>
     */
    private const CATEGORY_VIDEO_MAP = [
        'stress' => self::DEFAULT_VIDEO_URL,
        'anxiete' => self::VIDEO_ANXIETE_URL,
        'sommeil' => self::VIDEO_SOMMEIL_URL,
        'alimentation' => self::VIDEO_ALIMENTATION_URL,
        'nutrition' => self::VIDEO_ALIMENTATION_URL,
        'activite' => self::VIDEO_ACTIVITE_URL,
        'sport' => self::VIDEO_ACTIVITE_URL,
        'humeur' => self::VIDEO_HUMEUR_URL,
        'concentration' => self::VIDEO_CONCENTRATION_URL,
        'energie' => self::VIDEO_ENERGIE_URL,
        'bien_etre' => self::DEFAULT_VIDEO_URL,
        'bien-etre' => self::DEFAULT_VIDEO_URL,
        'bienetre' => self::DEFAULT_VIDEO_URL,
    ];

    /**
     * @param string[] $detectedProblems
     * @return Recommandation[]
     */
    public function getFiltered(Quiz $quiz, int $totalScore, array $detectedProblems): array
    {
        $normalizedProblems = $this->normalizeList($detectedProblems);
        $filtered = [];

        foreach ($quiz->getRecommandations()->toArray() as $recommandation) {
            if ($this->matchesRecommendationFilters($recommandation, $totalScore, $normalizedProblems)) {
                $filtered[] = $recommandation;
            }
        }

        return $this->sortBySeverity($filtered);
    }

    public function resolveVideoUrl(Recommandation $recommandation): ?string
    {
        $raw = trim((string) $recommandation->getVideoUrl());
        if ($raw !== '') {
            $youtubeEmbed = $this->toYoutubeEmbedUrl($raw);
            return $youtubeEmbed ?? $raw;
        }

        return $this->pickFallbackVideoUrl($recommandation);
    }

    /**
     * @param string[] $values
     * @return string[]
     */
    private function normalizeList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            $values
        ), static fn (string $value): bool => $value !== '')));
    }

    /**
     * @return string[]
     */
    private function extractTargetCategories(Recommandation $recommandation): array
    {
        $raw = (string) ($recommandation->getTargetCategories() ?? '');
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[,;|]+/', $raw) ?: [];
        $normalized = array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            $parts
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @param Recommandation[] $recommendations
     * @return Recommandation[]
     */
    private function sortBySeverity(array $recommendations): array
    {
        usort($recommendations, static function (Recommandation $a, Recommandation $b): int {
            $order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($order[$b->getSeverity()] ?? 1) <=> ($order[$a->getSeverity()] ?? 1);
        });

        return $recommendations;
    }

    /**
     * @param string[] $normalizedProblems
     */
    private function matchesRecommendationFilters(
        Recommandation $recommandation,
        int $totalScore,
        array $normalizedProblems
    ): bool {
        if (!$this->isScoreInRange($recommandation, $totalScore)) {
            return false;
        }

        if ($normalizedProblems === []) {
            return true;
        }

        return $this->matchesDetectedProblems($recommandation, $normalizedProblems);
    }

    private function isScoreInRange(Recommandation $recommandation, int $totalScore): bool
    {
        $minScore = (int) ($recommandation->getMinScore() ?? 0);
        $maxScore = (int) ($recommandation->getMaxScore() ?? PHP_INT_MAX);

        return $totalScore >= $minScore && $totalScore <= $maxScore;
    }

    /**
     * @param string[] $normalizedProblems
     */
    private function matchesDetectedProblems(Recommandation $recommandation, array $normalizedProblems): bool
    {
        $targetCategories = $this->extractTargetCategories($recommandation);
        $typeProbleme = mb_strtolower(trim((string) $recommandation->getTypeProbleme()));

        if ($targetCategories !== [] && array_intersect($targetCategories, $normalizedProblems) !== []) {
            return true;
        }

        foreach ($normalizedProblems as $problem) {
            if ($problem !== '' && str_contains($typeProbleme, $problem)) {
                return true;
            }
        }

        return $targetCategories === [] && $typeProbleme === '';
    }

    private function toYoutubeEmbedUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        $videoId = '';

        if (str_contains($host, 'youtu.be')) {
            $videoId = trim($path, '/');
        } elseif (str_contains($host, 'youtube.com')) {
            if (str_starts_with($path, '/embed/')) {
                $videoId = trim(substr($path, 7), '/');
            } elseif (str_starts_with($path, '/shorts/')) {
                $videoId = trim(substr($path, 8), '/');
            } elseif ($path === '/watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = is_string($query['v'] ?? null) ? $query['v'] : '';
            }
        }

        $videoId = preg_replace('/[^A-Za-z0-9_-]/', '', $videoId) ?? '';
        if ($videoId === '') {
            return null;
        }

        return 'https://www.youtube.com/embed/' . $videoId;
    }

    private function pickFallbackVideoUrl(Recommandation $recommandation): string
    {
        $categories = $this->extractTargetCategories($recommandation);

        $typeProbleme = trim((string) $recommandation->getTypeProbleme());
        if ($typeProbleme !== '') {
            $categories[] = mb_strtolower($typeProbleme);
        }

        foreach ($categories as $category) {
            $normalized = $this->normalizeCategory($category);
            if (isset(self::CATEGORY_VIDEO_MAP[$normalized])) {
                return self::CATEGORY_VIDEO_MAP[$normalized];
            }
        }

        return self::DEFAULT_VIDEO_URL;
    }

    private function normalizeCategory(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $normalized = $value;

        $rules = [
            'stress' => ['stress'],
            'anxiete' => ['anx'],
            'sommeil' => ['sommeil'],
            'alimentation' => ['aliment', 'nutri'],
            'activite' => ['activ', 'sport'],
            'humeur' => ['humeur', 'emotion'],
            'concentration' => ['concentr'],
            'energie' => ['energie', 'fatigue'],
            'bien_etre' => ['bien_etre', 'bien-etre', 'bienetre', 'bien etre'],
        ];

        foreach ($rules as $category => $tokens) {
            foreach ($tokens as $token) {
                if (str_contains($value, $token)) {
                    $normalized = $category;
                    break 2;
                }
            }
        }

        return $normalized;
    }
}
