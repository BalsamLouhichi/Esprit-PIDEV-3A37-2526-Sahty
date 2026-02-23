<?php

namespace App\Integration;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FastApiLabAiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpoint,
        private readonly string $ocrEngine,
        private readonly string $lang,
        private readonly string $model,
        private readonly bool $useOllama,
        private readonly string $ollamaMode,
        private readonly int $timeout,
        private readonly ?string $apiKey = null
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function analyzePdf(string $filePath, ?string $originalFilename = null): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException(sprintf('Fichier PDF introuvable: %s', $filePath));
        }

        if (!$this->endpoint) {
            throw new \RuntimeException('FASTAPI_AI_ENDPOINT n\'est pas configure.');
        }

        $filename = $originalFilename ?: basename($filePath);
        $useOllamaForRequest = $this->shouldUseOllamaForRequest();
        $primaryTimeout = $this->resolveTimeoutSeconds($useOllamaForRequest);

        $payload = $this->sendAnalyzeRequest(
            $filePath,
            $filename,
            $useOllamaForRequest,
            $primaryTimeout
        );

        if ($this->useOllama && !$useOllamaForRequest) {
            $payload['_integration_warning'] = sprintf(
                'Ollama enrichment skipped for latency stability (FASTAPI_AI_TIMEOUT=%ds).',
                (int) $this->timeout
            );
        }

        return $payload;
    }

    /**
     * @return array<string,mixed>
     */
    private function sendAnalyzeRequest(
        string $filePath,
        string $filename,
        bool $useOllama,
        int $effectiveTimeout
    ): array
    {
        $dataPart = DataPart::fromPath($filePath, $filename, 'application/pdf');
        $formData = new FormDataPart([
            'ocr_engine' => $this->ocrEngine,
            'lang' => $this->lang,
            'model' => $this->model,
            'use_ollama' => $useOllama ? 'true' : 'false',
            'ollama_mode' => $this->resolveOllamaModeForRequest($useOllama),
            'file' => $dataPart,
        ]);

        $headers = $formData->getPreparedHeaders()->toArray();
        if ($this->apiKey) {
            $headers[] = 'X-API-Key: ' . $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => $headers,
                'body' => $formData->bodyToIterable(),
                // timeout = idle timeout, max_duration = total request cap
                'timeout' => $effectiveTimeout,
                'max_duration' => $effectiveTimeout,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Echec de connexion au service IA distant: ' . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf(
                'Service IA distant en erreur (HTTP %d): %s',
                $statusCode,
                $content
            ));
        }

        $payload = json_decode($content, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Reponse IA invalide (JSON non parseable).');
        }

        return $payload;
    }

    private function resolveTimeoutSeconds(bool $useOllama): int
    {
        // Keep a protective cap but allow OCR/LLM workflows to finish on heavier PDFs.
        $configured = (int) $this->timeout;
        if ($configured <= 0) {
            return $useOllama ? 45 : 30;
        }

        $bounded = max(10, min($configured, 300));
        if ($useOllama) {
            // LLM mode is significantly slower in local environments.
            return max(30, $bounded);
        }

        return max(20, $bounded);
    }

    private function shouldUseOllamaForRequest(): bool
    {
        if (!$this->useOllama) {
            return false;
        }

        // The local FastAPI endpoint can take around 90s when Ollama is unreachable.
        // If the configured timeout is shorter, disable Ollama enrichment to avoid
        // systematic client-side timeouts and "failed" statuses in the app.
        $configured = (int) $this->timeout;
        if ($configured > 0 && $configured < 90) {
            return false;
        }

        return true;
    }

    private function resolveOllamaModeForRequest(bool $useOllama): string
    {
        if (!$useOllama) {
            return 'off';
        }

        $mode = strtolower(trim($this->ollamaMode));
        if ($mode === '') {
            return 'glossary_only';
        }

        return $mode;
    }
}
