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

        try {
            return $this->sendAnalyzeRequest(
                $filePath,
                $originalFilename ?: basename($filePath),
                $this->useOllama
            );
        } catch (\RuntimeException $firstError) {
            // Fallback safety: if LLM path times out, retry with use_ollama=false
            // to keep deterministic extraction working for email pipeline.
            if ($this->useOllama && $this->looksLikeTimeout($firstError->getMessage())) {
                $payload = $this->sendAnalyzeRequest(
                    $filePath,
                    $originalFilename ?: basename($filePath),
                    false
                );
                $payload['_integration_warning'] = 'LLM timeout: fallback executed with use_ollama=false.';
                return $payload;
            }
            throw $firstError;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function sendAnalyzeRequest(string $filePath, string $filename, bool $useOllama): array
    {
        $dataPart = DataPart::fromPath($filePath, $filename, 'application/pdf');
        $formData = new FormDataPart([
            'ocr_engine' => $this->ocrEngine,
            'lang' => $this->lang,
            'model' => $this->model,
            'use_ollama' => $useOllama ? 'true' : 'false',
            'file' => $dataPart,
        ]);

        $headers = $formData->getPreparedHeaders()->toArray();
        if ($this->apiKey) {
            $headers[] = 'X-API-Key: ' . $this->apiKey;
        }

        $effectiveTimeout = $this->resolveTimeoutSeconds();

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

    private function looksLikeTimeout(string $message): bool
    {
        $m = strtolower($message);
        return str_contains($m, 'timeout')
            || str_contains($m, 'idle timeout')
            || str_contains($m, 'timed out');
    }

    private function resolveTimeoutSeconds(): int
    {
        // Hard cap to prevent blocking upload requests too long.
        // Keep it short: if IA is slow, request fails fast and app continues.
        $configured = (int) $this->timeout;
        if ($configured <= 0) {
            return 12;
        }

        return max(5, min($configured, 15));
    }
}
