<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Utilisateur;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeCheckoutService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $stripeSecretKey = '',
        private readonly string $stripeWebhookSecret = ''
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->stripeSecretKey !== '';
    }

    public function hasWebhookSecret(): bool
    {
        return $this->stripeWebhookSecret !== '';
    }

    public function createCheckoutSession(
        Evenement $evenement,
        Utilisateur $user,
        string $successUrl,
        string $cancelUrl
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe n est pas configure.');
        }

        $unitAmount = (int) round(((float) ($evenement->getTarif() ?? 0)) * 100);
        if ($unitAmount <= 0) {
            throw new \RuntimeException('Le montant de l evenement est invalide.');
        }

        $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
            'auth_bearer' => $this->stripeSecretKey,
            'body' => [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'customer_email' => (string) $user->getEmail(),
                'client_reference_id' => (string) $user->getId(),
                'metadata[event_id]' => (string) $evenement->getId(),
                'metadata[user_id]' => (string) $user->getId(),
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'tnd',
                'line_items[0][price_data][unit_amount]' => $unitAmount,
                'line_items[0][price_data][product_data][name]' => (string) $evenement->getTitre(),
                'line_items[0][price_data][product_data][description]' => (string) ($evenement->getDescription() ?? 'Participation evenement'),
            ],
            'timeout' => 10,
        ]);

        $payload = $response->toArray(false);
        if (!isset($payload['id'], $payload['url'])) {
            throw new \RuntimeException('Creation de session Stripe impossible.');
        }

        return [
            'id' => (string) $payload['id'],
            'url' => (string) $payload['url'],
        ];
    }

    public function parseAndVerifyWebhook(string $payload, ?string $signatureHeader): array
    {
        if (!$this->hasWebhookSecret()) {
            throw new \RuntimeException('Webhook Stripe non configure.');
        }

        if (!$signatureHeader) {
            throw new \RuntimeException('Signature Stripe manquante.');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            [$k, $v] = array_pad(explode('=', trim($segment), 2), 2, null);
            if ($k && $v) {
                $parts[$k] = $v;
            }
        }

        $timestamp = $parts['t'] ?? null;
        $signature = $parts['v1'] ?? null;
        if (!$timestamp || !$signature) {
            throw new \RuntimeException('Signature Stripe invalide.');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            throw new \RuntimeException('Signature Stripe expiree.');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $this->stripeWebhookSecret);

        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Verification Stripe echouee.');
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || !isset($event['type'])) {
            throw new \RuntimeException('Payload Stripe invalide.');
        }

        return $event;
    }
}

