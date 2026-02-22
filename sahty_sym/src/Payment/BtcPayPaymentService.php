<?php

namespace App\Payment;

use App\Entity\Commande;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BtcPayPaymentService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @return array{invoiceId: string, checkoutUrl: string}
     */
    public function createInvoice(Commande $commande, string $redirectUrl): array
    {
        $baseUrl = rtrim((string) ($_ENV['BTCPAY_BASE_URL'] ?? ''), '/');
        $storeId = (string) ($_ENV['BTCPAY_STORE_ID'] ?? '');
        $apiKey = (string) ($_ENV['BTCPAY_API_KEY'] ?? '');

        if ($baseUrl === '' || $storeId === '' || $apiKey === '') {
            throw new \RuntimeException('Configuration BTCPay manquante.');
        }

        $payload = [
            'amount' => (float) $commande->getPrixTotal(),
            'currency' => 'EUR',
            'metadata' => [
                'orderId' => (string) $commande->getId(),
                'orderNumber' => (string) $commande->getNumero(),
                'buyerEmail' => (string) $commande->getEmail(),
            ],
            'checkout' => [
                'redirectURL' => $redirectUrl,
            ],
        ];

        $response = $this->httpClient->request('POST', $baseUrl . '/api/v1/stores/' . $storeId . '/invoices', [
            'headers' => [
                'Authorization' => 'token ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 25,
        ]);

        $data = $response->toArray(false);
        $invoiceId = (string) ($data['id'] ?? '');
        $checkoutUrl = (string) ($data['checkoutLink'] ?? '');

        if ($invoiceId === '' || $checkoutUrl === '') {
            throw new \RuntimeException('Creation de facture BTCPay invalide.');
        }

        return [
            'invoiceId' => $invoiceId,
            'checkoutUrl' => $checkoutUrl,
        ];
    }

    public function isWebhookValid(Request $request): bool
    {
        $secret = (string) ($_ENV['BTCPAY_WEBHOOK_SECRET'] ?? '');
        if ($secret === '') {
            return true;
        }

        $provided = (string) $request->headers->get('btcpay-sig', '');
        if ($provided === '') {
            return false;
        }

        // BTCPay can send signatures as "sha256=<hash>".
        if (str_contains($provided, '=')) {
            [$algorithm, $signature] = explode('=', $provided, 2);
            if (strtolower(trim($algorithm)) !== 'sha256') {
                return false;
            }
            $provided = $signature;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, strtolower(trim($provided)));
    }
}
