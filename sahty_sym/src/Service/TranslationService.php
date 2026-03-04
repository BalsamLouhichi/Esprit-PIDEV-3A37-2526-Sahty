<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    private const MAX_TEXTS_PER_CHUNK = 24;

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @param array<string, string> $texts
     * @param array<int, string> $targetLanguages
     * @return array{
     *   ok: bool,
     *   translations?: array<string, array<string, string>>,
     *   error?: string
     * }
     */
    public function translateBatch(array $texts, array $targetLanguages, string $sourceLanguage = 'auto'): array
    {
        $normalizedTexts = $this->normalizeTexts($texts);
        if ($normalizedTexts === []) {
            return ['ok' => false, 'error' => 'Aucun texte a traduire'];
        }

        $languages = $this->normalizeLanguages($targetLanguages);
        if ($languages === []) {
            return ['ok' => false, 'error' => 'Aucune langue cible valide'];
        }

        $provider = strtolower(trim((string) ($_ENV['APP_TRANSLATION_PROVIDER'] ?? $_ENV['APP_AI_GUIDANCE_PROVIDER'] ?? $_ENV['APP_DICTATION_PROVIDER'] ?? 'openai')));
        $endpoint = trim((string) ($_ENV['APP_TRANSLATION_ENDPOINT'] ?? $_ENV['APP_AI_GUIDANCE_ENDPOINT'] ?? ''));
        $apiKey = trim((string) ($_ENV['APP_TRANSLATION_API_KEY'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) ($_ENV['APP_DICTATION_API_KEY'] ?? ''));
        }
        if ($apiKey === '') {
            $apiKey = trim((string) ($_ENV['APP_AI_GUIDANCE_API_KEY'] ?? ''));
        }
        if ($apiKey === '') {
            $apiKey = trim((string) ($_ENV['APP_AI_RESULTAT_API_KEY'] ?? ''));
        }
        $model = trim((string) ($_ENV['APP_TRANSLATION_MODEL'] ?? $_ENV['APP_AI_GUIDANCE_MODEL'] ?? 'gpt-4o-mini'));

        if ($provider === 'openai' && $endpoint === '') {
            $endpoint = 'https://api.openai.com/v1/chat/completions';
        }
        if ($provider === 'huggingface' && $endpoint === '') {
            $endpoint = 'https://router.huggingface.co/v1/chat/completions';
        }
        if (!in_array($provider, ['openai', 'huggingface'], true)) {
            return ['ok' => false, 'error' => 'Provider de traduction non supporte (openai|huggingface)'];
        }
        if ($endpoint === '' || $apiKey === '' || $model === '') {
            return ['ok' => false, 'error' => 'Configuration traduction incomplete (endpoint/api_key/model)'];
        }

        $translations = [];
        foreach ($languages as $targetLanguage) {
            $result = $this->translateOneLanguage(
                $provider,
                $endpoint,
                $apiKey,
                $model,
                $normalizedTexts,
                $sourceLanguage,
                $targetLanguage
            );
            if (!$result['ok']) {
                return ['ok' => false, 'error' => (string) ($result['error'] ?? 'Erreur de traduction')];
            }
            $translations[$targetLanguage] = $result['texts'] ?? [];
        }

        return ['ok' => true, 'translations' => $translations];
    }

    /**
     * @param array<string, string> $texts
     * @return array{ok: bool, texts?: array<string, string>, error?: string}
     */
    private function translateOneLanguage(
        string $provider,
        string $endpoint,
        string $apiKey,
        string $model,
        array $texts,
        string $sourceLanguage,
        string $targetLanguage
    ): array {
        if (count($texts) > self::MAX_TEXTS_PER_CHUNK) {
            $merged = [];
            foreach (array_chunk($texts, self::MAX_TEXTS_PER_CHUNK, true) as $chunk) {
                $chunkResult = $this->translateOneLanguage(
                    $provider,
                    $endpoint,
                    $apiKey,
                    $model,
                    $chunk,
                    $sourceLanguage,
                    $targetLanguage
                );
                if (!$chunkResult['ok']) {
                    return $chunkResult;
                }
                $merged = array_replace($merged, (array) ($chunkResult['texts'] ?? []));
            }

            return ['ok' => true, 'texts' => $merged];
        }

        $systemPrompt = 'Tu es un traducteur medical fiable. Traduis sans ajouter ni retirer des informations. Reponds uniquement en JSON.';
        $userPrompt = sprintf(
            "Traduis les valeurs JSON ci-dessous.\n".
            "- Langue source: %s\n".
            "- Langue cible: %s\n".
            "- Conserver exactement les memes cles JSON\n".
            "- Garder style medical et sens clinique\n".
            "- Retourner STRICTEMENT un objet JSON {cle: texte_traduit}\n\n".
            "JSON source:\n%s",
            $sourceLanguage !== '' ? $sourceLanguage : 'auto',
            $targetLanguage,
            json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
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
                    'temperature' => 0.1,
                    'max_tokens' => 1800,
                ],
                'timeout' => 45,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($statusCode >= 400) {
                $providerError = trim((string) ($data['error']['message'] ?? $data['error'] ?? $data['message'] ?? ''));
                return ['ok' => false, 'error' => 'Erreur API traduction' . ($providerError !== '' ? ': ' . $providerError : '')];
            }

            $content = $this->extractAssistantContent($data);
            if ($content === '') {
                return ['ok' => false, 'error' => 'Reponse de traduction vide'];
            }

            $decoded = $this->parseJsonFromText($content);
            $translatedTexts = $this->hydrateTranslatedTextsFromDecoded($texts, $decoded);

            if (count($translatedTexts) < count($texts)) {
                $missingKeys = array_keys(array_diff_key($texts, $translatedTexts));
                $fromLooseText = $this->extractTranslationsFromLooseText($content, $missingKeys);
                foreach ($fromLooseText as $key => $value) {
                    $translatedTexts[$key] = $value;
                }
            }

            if (count($translatedTexts) < count($texts)) {
                $missingTexts = array_intersect_key($texts, array_diff_key($texts, $translatedTexts));
                $fallback = $this->translatePerKeyFallback(
                    $provider,
                    $endpoint,
                    $apiKey,
                    $model,
                    $missingTexts,
                    $sourceLanguage,
                    $targetLanguage
                );
                if ($fallback['ok'] === true) {
                    foreach ((array) ($fallback['texts'] ?? []) as $key => $value) {
                        $translatedTexts[(string) $key] = trim((string) $value);
                    }
                } else {
                    return ['ok' => false, 'error' => (string) ($fallback['error'] ?? 'Reponse de traduction invalide')];
                }
            }

            if (count($translatedTexts) < count($texts)) {
                return ['ok' => false, 'error' => 'Reponse de traduction invalide'];
            }

            $orderedTexts = [];
            foreach ($texts as $key => $_value) {
                $orderedTexts[$key] = trim((string) ($translatedTexts[$key] ?? ''));
            }

            return ['ok' => true, 'texts' => $orderedTexts];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Echec de traduction ' . $provider . ': ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, string> $texts
     * @return array{ok: bool, texts?: array<string, string>, error?: string}
     */
    private function translatePerKeyFallback(
        string $provider,
        string $endpoint,
        string $apiKey,
        string $model,
        array $texts,
        string $sourceLanguage,
        string $targetLanguage
    ): array {
        $translated = [];

        foreach ($texts as $key => $value) {
            $single = $this->translateSingleText(
                $provider,
                $endpoint,
                $apiKey,
                $model,
                $value,
                $sourceLanguage,
                $targetLanguage
            );
            if (!$single['ok']) {
                $singleError = trim((string) ($single['error'] ?? ''));
                return [
                    'ok' => false,
                    'error' => sprintf(
                        'Echec fallback pour la cle "%s"%s',
                        (string) $key,
                        $singleError !== '' ? ': ' . $singleError : ''
                    ),
                ];
            }
            $textValue = trim((string) ($single['text'] ?? ''));
            if ($textValue === '') {
                return [
                    'ok' => false,
                    'error' => sprintf('Traduction vide pour la cle "%s"', (string) $key),
                ];
            }
            $translated[$key] = $textValue;
        }

        return ['ok' => true, 'texts' => $translated];
    }

    /**
     * @return array{ok: bool, text?: string, error?: string}
     */
    private function translateSingleText(
        string $provider,
        string $endpoint,
        string $apiKey,
        string $model,
        string $text,
        string $sourceLanguage,
        string $targetLanguage
    ): array {
        $systemPrompt = 'Tu es un traducteur medical fiable. Reponds uniquement par le texte traduit, sans JSON, sans explication.';
        $userPrompt = sprintf(
            "Traduis ce texte.\nLangue source: %s\nLangue cible: %s\n\nTexte:\n%s",
            $sourceLanguage !== '' ? $sourceLanguage : 'auto',
            $targetLanguage,
            $text
        );

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
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
                    'temperature' => 0.1,
                    'max_tokens' => 500,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($statusCode >= 400) {
                $providerError = trim((string) ($data['error']['message'] ?? $data['error'] ?? $data['message'] ?? ''));
                return [
                    'ok' => false,
                    'error' => 'Erreur API traduction' . ($providerError !== '' ? ': ' . $providerError : ''),
                ];
            }

            $content = $this->extractAssistantContent($data);
            if ($content === '') {
                return ['ok' => false, 'error' => 'Reponse vide du provider'];
            }

            // Nettoie les fences markdown eventuels.
            $content = $this->stripMarkdownCodeFence($content);

            if ($content === '') {
                return ['ok' => false, 'error' => 'Reponse vide du provider'];
            }

            return ['ok' => true, 'text' => $content];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Echec de traduction ' . $provider . ': ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, string> $texts
     * @return array<string, string>
     */
    private function normalizeTexts(array $texts): array
    {
        $normalized = [];
        foreach ($texts as $key => $value) {
            $k = trim((string) $key);
            $v = trim((string) $value);
            if ($k !== '' && $v !== '') {
                $normalized[$k] = $v;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $languages
     * @return array<int, string>
     */
    private function normalizeLanguages(array $languages): array
    {
        $normalized = [];
        foreach ($languages as $language) {
            $lang = strtolower(trim((string) $language));
            if ($lang === '') {
                continue;
            }
            if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $lang) !== 1) {
                continue;
            }
            $normalized[] = $lang;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        $trimmed = $this->stripMarkdownCodeFence(trim($text));
        if ($trimmed === '') {
            return null;
        }

        $candidates = [$trimmed];
        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $candidates[] = (string) $matches[0];
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $sanitized = $this->sanitizeJsonCandidate($candidate);
            if ($sanitized !== $candidate) {
                $decoded = json_decode($sanitized, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
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

            if ($parts !== []) {
                return trim(implode("\n", $parts));
            }
        }

        $fallbackContent = $data['choices'][0]['text'] ?? $data['message']['content'] ?? $data['output'][0]['content'][0]['text'] ?? $data['output_text'] ?? null;
        return is_string($fallbackContent) ? trim($fallbackContent) : '';
    }

    private function stripMarkdownCodeFence(string $text): string
    {
        $clean = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', trim($text)) ?? trim($text);
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        return trim($clean);
    }

    private function sanitizeJsonCandidate(string $candidate): string
    {
        $clean = strtr($candidate, [
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{201E}" => '"',
            "\u{201F}" => '"',
            "\u{2019}" => "'",
            "\u{2018}" => "'",
            "\u{201A}" => "'",
            "\u{201B}" => "'",
        ]);

        $clean = preg_replace('/,\s*([}\]])/', '$1', $clean) ?? $clean;
        $clean = preg_replace('/\'([A-Za-z0-9_\-]+)\'\s*:/', '"$1":', $clean) ?? $clean;
        $clean = preg_replace_callback(
            '/:\s*\'((?:\\\\.|[^\'\\\\])*)\'/s',
            static function (array $matches): string {
                $decoded = stripcslashes((string) $matches[1]);
                $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return ': ' . ($encoded !== false ? $encoded : '""');
            },
            $clean
        ) ?? $clean;

        return $clean;
    }

    /**
     * @param array<string, string> $texts
     * @param array<string, mixed>|null $decoded
     * @return array<string, string>
     */
    private function hydrateTranslatedTextsFromDecoded(array $texts, ?array $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        $sources = [$decoded];
        foreach (['translations', 'data', 'result'] as $containerKey) {
            if (isset($decoded[$containerKey]) && is_array($decoded[$containerKey])) {
                $sources[] = $decoded[$containerKey];
            }
        }

        $translated = [];
        foreach ($sources as $source) {
            foreach ($texts as $key => $_value) {
                if (isset($translated[$key])) {
                    continue;
                }
                if (!array_key_exists($key, $source)) {
                    continue;
                }
                $value = $this->normalizeExtractedValue($source[$key]);
                if ($value !== '') {
                    $translated[$key] = $value;
                }
            }
        }

        return $translated;
    }

    /**
     * @param array<int, string> $expectedKeys
     * @return array<string, string>
     */
    private function extractTranslationsFromLooseText(string $content, array $expectedKeys): array
    {
        $translated = [];
        $normalizedContent = $this->stripMarkdownCodeFence($content);
        if ($normalizedContent === '') {
            return $translated;
        }

        foreach ($expectedKeys as $key) {
            $k = (string) $key;
            if ($k === '') {
                continue;
            }

            $quotedKey = preg_quote($k, '/');
            $patterns = [
                "/[\"']?" . $quotedKey . "[\"']?\\s*:\\s*\"((?:\\\\.|[^\"\\\\])*)\"/u",
                "/[\"']?" . $quotedKey . "[\"']?\\s*:\\s*'((?:\\\\.|[^'\\\\])*)'/u",
                "/[\"']?" . $quotedKey . "[\"']?\\s*:\\s*([^,\\r\\n}]+)/u",
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalizedContent, $matches) !== 1) {
                    continue;
                }

                $value = stripcslashes(trim((string) ($matches[1] ?? '')));
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if ($value !== '') {
                    $translated[$k] = $value;
                    break;
                }
            }
        }

        return $translated;
    }

    private function normalizeExtractedValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['text', 'content', 'value'] as $key) {
            if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                return trim((string) $value[$key]);
            }
        }

        return '';
    }
}
