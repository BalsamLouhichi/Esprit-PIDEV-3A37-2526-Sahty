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
        $result = $fallback;
        $apiKey = $this->resolveApiKey();

        if ($apiKey !== '') {
            $model = $this->resolveModel();
            $decoded = $this->requestRecommendationPayload(
                $apiKey,
                $model,
                $quizName,
                $score,
                $maxScore,
                $percentage,
                $detectedProblems,
                $existingRecommendations
            );

            if (is_array($decoded)) {
                $summary = trim((string) ($decoded['summary'] ?? ''));
                $disclaimer = trim((string) ($decoded['disclaimer'] ?? ''));
                $recommendations = $this->normalizeRecommendationLines($decoded['recommendations'] ?? null);

                if ($summary !== '' && $recommendations !== []) {
                    $result = [
                        'summary' => mb_substr($summary, 0, 400),
                        'recommendations' => $recommendations,
                        'videos' => $this->buildDiversifiedYoutubeSuggestions($recommendations, $detectedProblems),
                        'disclaimer' => $disclaimer !== '' ? mb_substr($disclaimer, 0, 220) : $fallback['disclaimer'],
                    ];
                }
            }
        }

        return $result;
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
            $query = $this->mapRecommendationLineToQuery($line);
            if ($query !== null) {
                $queries[] = $query;
            }
        }

        foreach ($detectedProblems as $problem) {
            $query = $this->mapProblemToQuery($problem);
            if ($query !== null) {
                $queries[] = $query;
            }
        }

        if ($queries === []) {
            $queries = $topicFallbacks;
        }
        $queries = array_values(array_unique($queries));

        return $this->buildYoutubeSearchLinks($queries, $channelHints);
    }

    private function resolveApiKey(): string
    {
        return trim((string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? ''));
    }

    private function resolveModel(): string
    {
        return trim((string) ($_ENV['QUIZ_AI_RECO_MODEL'] ?? $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'));
    }

    /**
     * @param array<int,string> $detectedProblems
     * @param array<int,string> $existingRecommendations
     * @return array<string,mixed>|null
     */
    private function requestRecommendationPayload(
        string $apiKey,
        string $model,
        string $quizName,
        int $score,
        int $maxScore,
        int $percentage,
        array $detectedProblems,
        array $existingRecommendations
    ): ?array {
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

        $decoded = null;

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
            if ($statusCode < 400) {
                $content = $this->extractAssistantContent($data);
                if ($content !== '') {
                    $payload = json_decode($content, true);
                    if (is_array($payload)) {
                        $decoded = $payload;
                    }
                }
            }
        } catch (\Throwable) {
            $decoded = null;
        }

        return $decoded;
    }

    /**
     * @return string[]
     */
    private function normalizeRecommendationLines(mixed $rawLines): array
    {
        if (!is_array($rawLines)) {
            return [];
        }

        $recommendations = [];
        foreach ($rawLines as $line) {
            if (!is_string($line)) {
                continue;
            }
            $line = trim($line);
            if ($line !== '') {
                $recommendations[] = $line;
            }
        }

        return array_slice(array_values(array_unique($recommendations)), 0, 5);
    }

    private function mapRecommendationLineToQuery(string $line): ?string
    {
        $token = mb_strtolower(trim($line), 'UTF-8');
        if ($token === '') {
            return null;
        }

        $query = 'bien etre mental conseils pratiques';
        if (str_contains($token, 'sommeil')) {
            $query = 'hygiene du sommeil insomnie conseils';
        } elseif (str_contains($token, 'respiration') || str_contains($token, 'apaisement')) {
            $query = 'exercices de respiration pour anxiete';
        } elseif (str_contains($token, 'stress')) {
            $query = 'gestion du stress techniques simples';
        } elseif (str_contains($token, 'professionnel') || str_contains($token, 'consultez')) {
            $query = 'quand consulter pour anxiete et stress';
        } elseif (str_contains($token, 'suivez') || str_contains($token, 'symptomes')) {
            $query = 'journal des symptomes anxiete comment faire';
        }

        return $query;
    }

    private function mapProblemToQuery(mixed $problem): ?string
    {
        $token = mb_strtolower(trim((string) $problem), 'UTF-8');
        if ($token === '') {
            return null;
        }

        $query = null;
        if (str_contains($token, 'anx')) {
            $query = 'anxiete comprendre et agir';
        } elseif (str_contains($token, 'stress')) {
            $query = 'stress chronique solutions';
        } elseif (str_contains($token, 'sommeil')) {
            $query = 'ameliorer sommeil routine';
        } elseif (str_contains($token, 'humeur')) {
            $query = 'stabiliser humeur habitudes';
        }

        return $query;
    }

    /**
     * @param string[] $queries
     * @param string[] $channelHints
     * @return array<int,array{title:string,url:string,channel_hint:string}>
     */
    private function buildYoutubeSearchLinks(array $queries, array $channelHints): array
    {
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
