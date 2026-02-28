<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuizAiRecommendationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @param array<int,string> $detectedProblems
     * @param array<int,string> $existingRecommendations
     * @return array{
     *   summary:string,
     *   recommendations:array<int,string>,
     *   videos:array<int,array{title:string,url:string,channel_hint:string}>,
     *   disclaimer:string
     * }
     */
    public function generate(
        string $quizName,
        int $score,
        int $maxScore,
        int $percentage,
        array $detectedProblems,
        array $existingRecommendations
    ): array {
        $fallback = $this->fallback($percentage, $detectedProblems);

        $apiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? ''));
        $model = trim((string) ($_ENV['QUIZ_AI_RECO_MODEL'] ?? $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'));
        if ($apiKey === '') {
            return $fallback;
        }

        $problemsText = $detectedProblems ? implode(', ', $detectedProblems) : 'Aucune categorie specifique detectee';
        $existingText = $existingRecommendations ? implode("\n- ", $existingRecommendations) : 'Aucune';

        $systemPrompt = 'Tu es un assistant de prevention sante. '
            . 'Tu proposes des recommandations non diagnostiques, concretes, courtes, en francais simple. '
            . 'Ne donne pas de diagnostic medical. '
            . 'Reponds en JSON strict avec les cles: summary (string), recommendations (array de 3 a 5 strings), disclaimer (string).';

        $userPrompt = sprintf(
            "Quiz: %s\nScore: %d/%d (%d%%)\nCategories detectees: %s\nRecommandations existantes:\n- %s\n\nGenere une version IA complementaire concise.",
            $quizName !== '' ? $quizName : 'Quiz sante',
            $score,
            $maxScore,
            $percentage,
            $problemsText,
            $existingText
        );

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 500,
                    'response_format' => ['type' => 'json_object'],
                ],
                'timeout' => 25,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($statusCode >= 400 || !is_array($data)) {
                return $fallback;
            }

            $content = $this->extractAssistantContent($data);
            if ($content === '') {
                return $fallback;
            }

            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return $fallback;
            }

            $summary = trim((string) ($decoded['summary'] ?? ''));
            $disclaimer = trim((string) ($decoded['disclaimer'] ?? ''));
            $recommendations = [];
            if (is_array($decoded['recommendations'] ?? null)) {
                foreach ($decoded['recommendations'] as $line) {
                    if (!is_string($line)) {
                        continue;
                    }
                    $line = trim($line);
                    if ($line !== '') {
                        $recommendations[] = $line;
                    }
                }
            }

            if ($summary === '' || $recommendations === []) {
                return $fallback;
            }

        return [
            'summary' => mb_substr($summary, 0, 400),
            'recommendations' => array_slice(array_values(array_unique($recommendations)), 0, 5),
            'videos' => $this->buildDiversifiedYoutubeSuggestions(
                array_slice(array_values(array_unique($recommendations)), 0, 5),
                $detectedProblems
            ),
            'disclaimer' => $disclaimer !== '' ? mb_substr($disclaimer, 0, 220) : $fallback['disclaimer'],
        ];
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function extractAssistantContent(array $data): string
    {
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $parts[] = trim($item);
                    continue;
                }
                if (!is_array($item)) {
                    continue;
                }
                $text = $item['text'] ?? $item['content'] ?? $item['value'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    $parts[] = trim($text);
                }
            }

            return trim(implode(' ', $parts));
        }

        return '';
    }

    /**
     * @param array<int,string> $detectedProblems
     * @return array{
     *   summary:string,
     *   recommendations:array<int,string>,
     *   videos:array<int,array{title:string,url:string,channel_hint:string}>,
     *   disclaimer:string
     * }
     */
    private function fallback(int $percentage, array $detectedProblems): array
    {
        $summary = 'Recommandation IA indisponible, voici une version guidee locale.';
        if ($percentage >= 70) {
            $summary = 'Votre score est eleve: mettez en place rapidement une routine d apaisement et un suivi professionnel.';
        } elseif ($percentage >= 40) {
            $summary = 'Votre score est modere: des ajustements progressifs peuvent nettement ameliorer votre quotidien.';
        } elseif ($percentage > 0) {
            $summary = 'Votre score est plutot faible: continuez vos habitudes de prevention.';
        }

        $problemLine = $detectedProblems
            ? 'Cibles prioritaires: ' . implode(', ', $detectedProblems) . '.'
            : 'Cibles prioritaires: bien-etre general.';

        $recommendations = [
            $problemLine,
            'Planifiez une routine hebdomadaire: sommeil regulier, activite physique douce, pauses ecran.',
            'Suivez vos symptomes 2 a 3 semaines puis comparez votre evolution.',
            'Consultez un professionnel si les symptomes persistent ou s aggravent.',
        ];

        return [
            'summary' => $summary,
            'recommendations' => $recommendations,
            'videos' => $this->buildDiversifiedYoutubeSuggestions($recommendations, $detectedProblems),
            'disclaimer' => 'Conseils d orientation uniquement. Cela ne remplace pas un avis medical.',
        ];
    }

    /**
     * @param array<int,string> $recommendations
     * @param array<int,string> $detectedProblems
     * @return array<int,array{title:string,url:string,channel_hint:string}>
     */
    private function buildDiversifiedYoutubeSuggestions(array $recommendations, array $detectedProblems): array
    {
        $channelHints = [
            'TEDx Talks',
            'Huberman Lab',
            'Yoga With Adriene',
            'Psych2Go',
            'HAS sante mentale',
        ];

        $topicFallbacks = [
            'respiration anxiete exercice',
            'meditation guidee anxiete',
            'hygiene du sommeil conseils',
            'gestion du stress quotidien',
            'techniques TCC anxiete',
        ];

        $queries = [];
        foreach ($recommendations as $line) {
            $lineToken = mb_strtolower(trim($line), 'UTF-8');
            if ($lineToken === '') {
                continue;
            }

            if (str_contains($lineToken, 'sommeil')) {
                $queries[] = 'hygiene du sommeil insomnie conseils';
            } elseif (str_contains($lineToken, 'respiration') || str_contains($lineToken, 'apaisement')) {
                $queries[] = 'exercices de respiration pour anxiete';
            } elseif (str_contains($lineToken, 'stress')) {
                $queries[] = 'gestion du stress techniques simples';
            } elseif (str_contains($lineToken, 'professionnel') || str_contains($lineToken, 'consultez')) {
                $queries[] = 'quand consulter pour anxiete et stress';
            } elseif (str_contains($lineToken, 'suivez') || str_contains($lineToken, 'symptomes')) {
                $queries[] = 'journal des symptomes anxiete comment faire';
            } else {
                $queries[] = 'bien etre mental conseils pratiques';
            }
        }

        foreach ($detectedProblems as $problem) {
            $p = mb_strtolower(trim((string) $problem), 'UTF-8');
            if ($p === '') {
                continue;
            }
            if (str_contains($p, 'anx')) {
                $queries[] = 'anxiete comprendre et agir';
            } elseif (str_contains($p, 'stress')) {
                $queries[] = 'stress chronique solutions';
            } elseif (str_contains($p, 'sommeil')) {
                $queries[] = 'ameliorer sommeil routine';
            } elseif (str_contains($p, 'humeur')) {
                $queries[] = 'stabiliser humeur habitudes';
            }
        }

        if ($queries === []) {
            $queries = $topicFallbacks;
        }

        $queries = array_values(array_unique($queries));
        $videos = [];
        $usedChannels = [];

        for ($i = 0; $i < min(5, count($queries)); $i++) {
            $channel = $channelHints[$i % count($channelHints)];
            if (isset($usedChannels[$channel])) {
                $channel = $channelHints[($i + 2) % count($channelHints)];
            }
            $usedChannels[$channel] = true;

            $query = trim($queries[$i] . ' ' . $channel);
            $videos[] = [
                'title' => 'YouTube: ' . $queries[$i],
                'url' => 'https://www.youtube.com/results?search_query=' . rawurlencode($query),
                'channel_hint' => $channel,
            ];
        }

        return $videos;
    }
}
