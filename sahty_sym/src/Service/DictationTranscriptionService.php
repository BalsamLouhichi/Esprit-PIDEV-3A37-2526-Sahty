<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DictationTranscriptionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @return array{ok: bool, text?: string, error?: string}
     */
    public function transcribe(UploadedFile $audioFile, string $language = 'fr'): array
    {
        $provider = strtolower((string) ($_ENV['APP_DICTATION_PROVIDER'] ?? $_ENV['APP_AI_RESULTAT_PROVIDER'] ?? 'openai'));
        $rawEndpoint = (string) ($_ENV['APP_DICTATION_ENDPOINT'] ?? $_ENV['APP_AI_RESULTAT_ENDPOINT'] ?? 'https://api.openai.com/v1/audio/transcriptions');
        $endpoint = $this->normalizeEndpoint($rawEndpoint);
        if ($endpoint === '' && $provider === 'huggingface') {
            $endpoint = 'https://router.huggingface.co/hf-inference/models/openai/whisper-large-v3-turbo';
        }
        if ($endpoint === '' && $provider === 'openai') {
            $endpoint = 'https://api.openai.com/v1/audio/transcriptions';
        }
        $apiKey = trim((string) ($_ENV['APP_DICTATION_API_KEY'] ?? ''));
        $fallbackApiKey = trim((string) ($_ENV['APP_AI_RESULTAT_API_KEY'] ?? ''));
        if ($apiKey === '' && $provider === 'openai') {
            $apiKey = $fallbackApiKey;
        }
        if ($apiKey === '' && $provider === 'huggingface' && str_starts_with($fallbackApiKey, 'hf_')) {
            $apiKey = $fallbackApiKey;
        }
        $model = (string) ($_ENV['APP_DICTATION_MODEL'] ?? $_ENV['APP_AI_RESULTAT_MODEL'] ?? 'gpt-4o-mini-transcribe');

        if ($endpoint === '') {
            return ['ok' => false, 'error' => 'APP_DICTATION_ENDPOINT / APP_AI_RESULTAT_ENDPOINT non configure'];
        }
        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return ['ok' => false, 'error' => 'Endpoint de dictee invalide: ' . $endpoint];
        }
        if ($apiKey === '') {
            if ($provider === 'huggingface') {
                return ['ok' => false, 'error' => 'APP_DICTATION_API_KEY (hf_...) non configure pour Hugging Face'];
            }
            return ['ok' => false, 'error' => 'APP_DICTATION_API_KEY / APP_AI_RESULTAT_API_KEY non configure'];
        }
        if ($provider === 'huggingface' && !str_starts_with($apiKey, 'hf_')) {
            return ['ok' => false, 'error' => 'Cle API Hugging Face invalide (attendu: hf_...)'];
        }
        $safeFilename = $audioFile->getClientOriginalName() ?: 'dictation.webm';
        $detectedMimeType = strtolower((string) ($audioFile->getMimeType() ?: $audioFile->getClientMimeType() ?: 'audio/webm'));
        $ext = strtolower((string) ($audioFile->guessExtension() ?: pathinfo($safeFilename, PATHINFO_EXTENSION)));
        $mimeType = match (true) {
            $detectedMimeType === 'video/webm' => 'audio/webm',
            $detectedMimeType === 'video/mp4' => 'audio/mp4',
            $detectedMimeType === 'application/octet-stream' && $ext === 'ogg' => 'audio/ogg',
            $detectedMimeType === 'application/octet-stream' && $ext === 'wav' => 'audio/wav',
            $detectedMimeType === 'application/octet-stream' && ($ext === 'mp3' || $ext === 'mpeg') => 'audio/mpeg',
            $detectedMimeType === 'application/octet-stream' && ($ext === 'm4a' || $ext === 'mp4') => 'audio/mp4',
            $detectedMimeType === 'application/octet-stream' => 'audio/webm',
            default => $detectedMimeType,
        };

        if ($provider === 'huggingface') {
            // Always build a safe HF inference URL from model to avoid malformed env endpoint values.
            $endpoint = $this->resolveHuggingFaceEndpoint('', $model);
            if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
                return ['ok' => false, 'error' => 'Endpoint Hugging Face invalide: ' . $endpoint];
            }
            return $this->transcribeWithHuggingFace($audioFile, $endpoint, $apiKey, $mimeType);
        }
        if ($provider !== 'openai') {
            return ['ok' => false, 'error' => 'Provider de dictee non supporte (openai|huggingface)'];
        }

        $formFields = [
            'model' => $model,
            'language' => trim($language) !== '' ? $language : 'fr',
            'response_format' => 'json',
            'file' => DataPart::fromPath($audioFile->getPathname(), $safeFilename, $mimeType),
        ];
        $formData = new FormDataPart($formFields);

        try {
            $attempts = 3;
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $response = $this->httpClient->request('POST', $endpoint, [
                    'headers' => array_merge(
                        ['Authorization' => 'Bearer ' . $apiKey],
                        $formData->getPreparedHeaders()->toArray()
                    ),
                    'body' => $formData->bodyToIterable(),
                    'timeout' => 60,
                ]);

                $statusCode = $response->getStatusCode();
                $data = $response->toArray(false);
                $providerError = '';
                if (is_array($data)) {
                    $providerError = trim((string) (($data['error']['message'] ?? $data['message'] ?? '')));
                }

                if (($statusCode === 429 || $statusCode >= 500) && $attempt < $attempts) {
                    $headers = $response->getHeaders(false);
                    $retryAfterHeader = (string) (($headers['retry-after'][0] ?? ''));
                    $retryAfterSeconds = ctype_digit($retryAfterHeader) ? (int) $retryAfterHeader : 0;
                    $delaySeconds = $retryAfterSeconds > 0 ? $retryAfterSeconds : $attempt;
                    usleep($delaySeconds * 1000000);
                    continue;
                }

                if ($statusCode >= 400) {
                    if ($statusCode === 429) {
                        return ['ok' => false, 'error' => 'Quota/rate limit OpenAI atteint' . ($providerError !== '' ? ': ' . $providerError : '')];
                    }

                    return ['ok' => false, 'error' => 'Erreur API de transcription' . ($providerError !== '' ? ': ' . $providerError : '')];
                }
                if (!is_array($data)) {
                    return ['ok' => false, 'error' => 'Reponse de transcription invalide'];
                }

                $text = trim((string) ($data['text'] ?? ''));
                if ($text === '') {
                    return ['ok' => false, 'error' => 'Transcription vide'];
                }

                return ['ok' => true, 'text' => $text];
            }

            return ['ok' => false, 'error' => 'Erreur API de transcription apres plusieurs tentatives'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Echec de la transcription distante: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, text?: string, error?: string}
     */
    private function transcribeWithHuggingFace(UploadedFile $audioFile, string $endpoint, string $apiKey, string $mimeType): array
    {
        try {
            $attempts = 3;
            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $response = $this->httpClient->request('POST', $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => $mimeType,
                        'Accept' => 'application/json',
                    ],
                    'body' => fopen($audioFile->getPathname(), 'rb'),
                    'timeout' => 120,
                ]);

                $statusCode = $response->getStatusCode();
                $rawBody = $response->getContent(false);
                $data = json_decode($rawBody, true);
                if (!is_array($data)) {
                    $data = [];
                }

                if (($statusCode === 429 || $statusCode === 503 || $statusCode >= 500) && $attempt < $attempts) {
                    $delaySeconds = min($attempt * 2, 6);
                    usleep($delaySeconds * 1000000);
                    continue;
                }

                if ($statusCode >= 400) {
                    $providerError = '';
                    $providerError = trim((string) ($data['error'] ?? $data['message'] ?? ''));
                    if ($providerError === '') {
                        $providerError = trim(substr(strip_tags($rawBody), 0, 200));
                    }
                    return ['ok' => false, 'error' => 'Erreur API de transcription HF' . ($providerError !== '' ? ': ' . $providerError : '')];
                }

                $text = trim((string) ($data['text'] ?? ($data[0]['text'] ?? '')));
                if ($text === '' && $rawBody !== '') {
                    // Some HF deployments may return plain text directly.
                    $text = trim($rawBody);
                }
                if ($text === '') {
                    return ['ok' => false, 'error' => 'Transcription vide'];
                }

                return ['ok' => true, 'text' => $text];
            }

            return ['ok' => false, 'error' => 'Erreur HF apres plusieurs tentatives'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Echec HF: ' . $e->getMessage()];
        }
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        $endpoint = trim($endpoint, "\"'");
        // Remove hidden unicode/control/separator chars that can break URL parsing.
        $endpoint = preg_replace('/[\p{C}\p{Z}]+/u', '', $endpoint) ?? '';

        return trim($endpoint);
    }

    private function resolveHuggingFaceEndpoint(string $endpoint, string $model): string
    {
        $endpoint = $this->normalizeEndpoint($endpoint);
        if (filter_var($endpoint, FILTER_VALIDATE_URL) !== false) {
            return $endpoint;
        }

        $model = trim($model);
        if ($model === '') {
            $model = 'openai/whisper-large-v3-turbo';
        }

        $segments = array_values(array_filter(explode('/', $model), static fn (string $part): bool => $part !== ''));
        $encodedModelPath = implode('/', array_map(static fn (string $part): string => rawurlencode($part), $segments));
        if ($encodedModelPath === '') {
            $encodedModelPath = 'openai/whisper-large-v3-turbo';
        }

        return 'https://router.huggingface.co/hf-inference/models/' . $encodedModelPath;
    }
}
