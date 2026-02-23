<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
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
            if (!($result['ok'] ?? false)) {
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
            if ($statusCode >= 400 || !is_array($data)) {
                $providerError = trim((string) ($data['error']['message'] ?? $data['error'] ?? $data['message'] ?? ''));
                return ['ok' => false, 'error' => 'Erreur API traduction' . ($providerError !== '' ? ': ' . $providerError : '')];
            }

            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            $decoded = $this->parseJsonFromText($content);
            if (!is_array($decoded)) {
                // Fallback robuste: traduire cle par cle si la reponse JSON globale est invalide.
                $fallback = $this->translatePerKeyFallback(
                    $provider,
                    $endpoint,
                    $apiKey,
                    $model,
                    $texts,
                    $sourceLanguage,
                    $targetLanguage
                );
                if (($fallback['ok'] ?? false) === true) {
                    return $fallback;
                }
                return ['ok' => false, 'error' => 'Reponse de traduction invalide'];
            }

            $translatedTexts = [];
            foreach ($texts as $key => $_value) {
                $translatedTexts[$key] = trim((string) ($decoded[$key] ?? ''));
            }

            return ['ok' => true, 'texts' => $translatedTexts];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Echec de traduction ' . $provider . ': ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, string> $texts
     * @return array{ok: bool, texts?: array<string, string>}
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
            if (!($single['ok'] ?? false)) {
                return ['ok' => false];
            }
            $translated[$key] = (string) ($single['text'] ?? '');
        }

        return ['ok' => true, 'texts' => $translated];
    }

    /**
     * @return array{ok: bool, text?: string}
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
            if ($statusCode >= 400 || !is_array($data)) {
                return ['ok' => false];
            }

            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                return ['ok' => false];
            }

            // Nettoie les fences markdown eventuels.
            $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
            $content = trim($content);

            return ['ok' => $content !== '', 'text' => $content];
        } catch (\Throwable) {
            return ['ok' => false];
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
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode((string) $matches[0], true);
        return is_array($decoded) ? $decoded : null;
    }
}
