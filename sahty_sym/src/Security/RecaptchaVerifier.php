<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecaptchaVerifier
{
    private HttpClientInterface $httpClient;
    private string $secretKey;
    private bool $enabled;
    private ?LoggerInterface $logger;
    private array $lastError = [];

    public function __construct(
        HttpClientInterface $httpClient,
        string $secretKey,
        bool $enabled,
        ?LoggerInterface $logger = null
    )
    {
        $this->httpClient = $httpClient;
        $this->secretKey = $secretKey;
        $this->enabled = $enabled;
        $this->logger = $logger;
    }

    public function verifyV3(?string $token, string $expectedAction, float $minScore = 0.5, ?string $remoteIp = null): bool
    {
        $this->lastError = [];

        if (!$this->enabled) {
            return true;
        }

        if (!$token) {
            $this->lastError = ['reason' => 'missing_token'];
            return false;
        }

        $body = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];

        if ($remoteIp) {
            $body['remoteip'] = $remoteIp;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => $body,
            ]);
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->lastError = ['reason' => 'request_failed', 'exception' => $e->getMessage()];
            $this->logger?->warning('reCAPTCHA verification request failed', $this->lastError);
            return false;
        }

        if (!isset($data['success']) || $data['success'] !== true) {
            $this->lastError = [
                'reason' => 'verification_failed',
                'errorCodes' => $data['error-codes'] ?? [],
                'hostname' => $data['hostname'] ?? null,
            ];
            $this->logger?->warning('reCAPTCHA verification failed', $this->lastError);
            return false;
        }

        if (!isset($data['action']) || $data['action'] !== $expectedAction) {
            $this->lastError = [
                'reason' => 'action_mismatch',
                'expected' => $expectedAction,
                'action' => $data['action'] ?? null,
                'hostname' => $data['hostname'] ?? null,
            ];
            $this->logger?->warning('reCAPTCHA action mismatch', $this->lastError);
            return false;
        }

        if (!isset($data['score']) || !is_numeric($data['score'])) {
            $this->lastError = [
                'reason' => 'missing_score',
                'action' => $data['action'] ?? null,
                'hostname' => $data['hostname'] ?? null,
            ];
            $this->logger?->warning('reCAPTCHA score missing', $this->lastError);
            return false;
        }

        if ((float) $data['score'] < $minScore) {
            $this->lastError = [
                'reason' => 'low_score',
                'score' => (float) $data['score'],
                'minScore' => $minScore,
                'action' => $data['action'] ?? null,
                'hostname' => $data['hostname'] ?? null,
            ];
            $this->logger?->warning('reCAPTCHA score too low', $this->lastError);
            return false;
        }

        return true;
    }

    public function getLastError(): array
    {
        return $this->lastError;
    }
}
