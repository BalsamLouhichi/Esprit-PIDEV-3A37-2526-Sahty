<?php

namespace App\Integration;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FastApiSemanticSearchClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(FASTAPI_SEMANTIC_ENDPOINT)%')]
        private readonly string $endpoint,
        #[Autowire('%env(int:FASTAPI_SEMANTIC_TIMEOUT)%')]
        private readonly int $timeout,
        #[Autowire('%env(default::FASTAPI_SEMANTIC_API_KEY)%')]
        private readonly ?string $apiKey = null
    ) {
    }

    /**
     * @param string[] $semanticKeywords
     * @return int[]
     */
    public function searchProductIds(string $query, int $limit = 30, array $semanticKeywords = []): array
    {
        $query = trim($query);
        if ($query === '' || trim($this->endpoint) === '') {
            return [];
        }

        $payload = [
            'query' => $query,
            'limit' => max(1, min(100, $limit)),
            'semantic_keywords' => array_values(array_filter(array_map(
                static fn (string $item): string => trim($item),
                $semanticKeywords
            ))),
            'active_only' => true,
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($this->apiKey) {
            $headers[] = 'X-API-Key: ' . $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => $headers,
                'json' => $payload,
                'timeout' => $this->resolveTimeoutSeconds(),
                'max_duration' => $this->resolveTimeoutSeconds(),
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return [];
            }

            $content = $response->getContent(false);
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                return [];
            }

            $ids = $decoded['product_ids'] ?? null;
            if (!is_array($ids)) {
                return [];
            }
        } catch (TransportExceptionInterface) {
            return [];
        } catch (\Throwable) {
            return [];
        }

        $filtered = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $filtered[] = $id;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function resolveTimeoutSeconds(): int
    {
        if ($this->timeout <= 0) {
            return 5;
        }

        return max(3, min($this->timeout, 20));
    }
}
