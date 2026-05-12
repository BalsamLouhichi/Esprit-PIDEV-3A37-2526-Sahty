<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaDefinitionService
{
    private const DEFAULT_MARKERS = [
        'CRP',
        'HGB',
        'WBC',
        'RBC',
        'PLT',
        'GLU',
        'CREA',
        'UREE',
        'ALT',
        'AST',
        'TSH',
    ];

    /**
     * @var array<string,string>
     */
    private array $builtInDefinitions = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private AdapterInterface $cache,
        private LoggerInterface $logger,
        private string $ollamaBaseUrl,
        private string $preferredModel,
        private int $connectTimeout = 4,
        private int $chatTimeout = 30,
        private int $tagsTimeout = 8
    ) {
        $this->seedBuiltInDefinitions();
    }

    public function getDefinition(string $marker): string
    {
        $normalized = $this->normalize($marker);
        if ($normalized === '') {
            return 'Marqueur biologique indisponible.';
        }

        $cacheKey = 'ollama_definition_' . md5($normalized);
        $cachedItem = $this->cache->getItem($cacheKey);
        if ($cachedItem->isHit()) {
            $cached = $cachedItem->get();
            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }
        }

        if (isset($this->builtInDefinitions[$normalized])) {
            $definition = $this->builtInDefinitions[$normalized];
            $this->saveCache($cacheKey, $definition, 86400);

            return $definition;
        }

        $definition = $this->fetchDefinition($marker);
        if ($this->isCacheableDefinition($definition)) {
            $this->saveCache($cacheKey, $definition, 86400);
        }

        return $definition;
    }

    /**
     * @param array<int,string>|null $markers
     */
    public function warmUpDefinitions(?array $markers = null): void
    {
        $markers = $markers ?: self::DEFAULT_MARKERS;

        foreach ($markers as $marker) {
            $cleanMarker = trim((string) $marker);
            if ($cleanMarker === '') {
                continue;
            }

            try {
                $this->getDefinition($cleanMarker);
            } catch (\Throwable $e) {
                $this->logger->warning('Ollama definition warmup failed.', [
                    'marker' => $cleanMarker,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function fetchDefinition(string $marker): string
    {
        $resolvedModel = $this->resolveAvailableModel();
        if ($resolvedModel === null) {
            return 'Aucun modele Ollama installe. Installez par exemple: ollama pull gemma3:4b';
        }

        $payload = [
            'model' => $resolvedModel,
            'stream' => false,
            'options' => [
                'temperature' => 0.1,
                'num_predict' => 80,
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu expliques les marqueurs biologiques simplement, sans jargon inutile.',
                ],
                [
                    'role' => 'user',
                    'content' => sprintf(
                        "Donne une definition medicale tres courte (2 phrases max, en francais simple) du marqueur biologique : '%s'.",
                        trim($marker)
                    ),
                ],
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl('/api/chat'), [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
                'timeout' => $this->chatTimeout,
                'max_duration' => $this->chatTimeout,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return sprintf('Definition indisponible via Ollama (%d).', $statusCode);
            }

            $body = json_decode($response->getContent(false), true);
            if (!is_array($body)) {
                return 'Definition indisponible pour ce marqueur.';
            }

            $text = $this->extractFirstContent($body);

            return $text !== '' ? $text : 'Definition indisponible pour ce marqueur.';
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Ollama definition request failed.', [
                'marker' => $marker,
                'error' => $e->getMessage(),
            ]);

            if (str_contains(strtolower($e->getMessage()), 'tim')) {
                return 'Ollama met trop de temps a repondre. Le modele local est probablement en cours de chargement.';
            }

            return 'Ollama n\'est pas accessible. Lancez le service local puis reessayez.';
        }
    }

    private function resolveAvailableModel(): ?string
    {
        $installedModels = $this->fetchInstalledModels();
        if ($installedModels === []) {
            return null;
        }

        if (in_array($this->preferredModel, $installedModels, true)) {
            return $this->preferredModel;
        }

        $preferredModels = [
            'gemma3:4b',
            'gemma3:latest',
            'gemma3:2b',
            'gemma3:1b',
            'gemma3',
            'llama3.2:3b',
            'llama3.2',
            'llama3:latest',
            'llama3',
            'mistral:latest',
            'mistral',
        ];

        foreach ($preferredModels as $model) {
            if (in_array($model, $installedModels, true)) {
                return $model;
            }
        }

        return $installedModels[0] ?? null;
    }

    /**
     * @return array<int,string>
     */
    private function fetchInstalledModels(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->buildUrl('/api/tags'), [
                'timeout' => $this->tagsTimeout,
                'max_duration' => $this->tagsTimeout,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return [];
            }

            $body = json_decode($response->getContent(false), true);
            if (!is_array($body)) {
                return [];
            }

            $models = [];
            foreach ((array) ($body['models'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $name = trim((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    $models[] = $name;
                }
            }

            return array_values(array_unique($models));
        } catch (ExceptionInterface $e) {
            $this->logger->info('Could not fetch installed Ollama models.', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string,mixed> $body
     */
    private function extractFirstContent(array $body): string
    {
        $message = $body['message'] ?? null;
        if (is_array($message)) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content !== '') {
                return preg_replace('/\s+/', ' ', $content) ?? $content;
            }
        }

        $rawContent = trim((string) ($body['response'] ?? ''));
        if ($rawContent !== '') {
            return preg_replace('/\s+/', ' ', $rawContent) ?? $rawContent;
        }

        return '';
    }

    private function seedBuiltInDefinitions(): void
    {
        $definitions = [
            'CRP' => 'La CRP est une proteine de l inflammation. Un taux eleve peut indiquer une infection ou une reaction inflammatoire.',
            'Créatinine' => 'La creatinine est un dechet filtre par les reins. Un taux eleve peut suggerer une atteinte du fonctionnement renal.',
            'Creatinine' => 'La creatinine est un dechet filtre par les reins. Un taux eleve peut suggerer une atteinte du fonctionnement renal.',
            'HGB' => 'L HGB represente l hemoglobine, qui transporte l oxygene dans le sang. Une valeur basse peut orienter vers une anemie.',
            'WBC' => 'Les WBC correspondent aux globules blancs. Une augmentation peut etre liee a une infection ou a une inflammation.',
            'RBC' => 'Les RBC correspondent aux globules rouges. Une baisse peut etre associee a une anemie ou a une perte sanguine.',
            'PLT' => 'Les PLT sont les plaquettes, utiles a la coagulation. Un taux anormal peut modifier le risque de saignement ou de thrombose.',
            'GLU' => 'Le glucose represente le taux de sucre dans le sang. Une valeur elevee peut faire evoquer un desequilibre glycemique.',
            'UREE' => 'L uree est un dechet elimine par les reins. Une valeur elevee peut etre observee en cas de deshydratation ou d atteinte renale.',
            'ALT' => 'L ALT est une enzyme du foie. Un taux eleve peut traduire une irritation ou une atteinte hepatique.',
            'AST' => 'L AST est une enzyme presente notamment dans le foie et les muscles. Un taux eleve peut orienter vers une atteinte hepatique ou musculaire.',
            'TSH' => 'La TSH est une hormone qui regule la thyroide. Une valeur anormale peut indiquer un trouble thyroidien.',
        ];

        foreach ($definitions as $marker => $definition) {
            $this->builtInDefinitions[$this->normalize($marker)] = $definition;
        }
    }

    private function saveCache(string $cacheKey, string $value, int $ttl): void
    {
        $item = $this->cache->getItem($cacheKey);
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->cache->save($item);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function isCacheableDefinition(string $definition): bool
    {
        $normalized = mb_strtolower(trim($definition));
        if ($normalized === '') {
            return false;
        }

        return !str_starts_with($normalized, 'ollama n\'est pas accessible')
            && !str_starts_with($normalized, 'ollama met trop de temps')
            && !str_starts_with($normalized, 'definition indisponible')
            && !str_starts_with($normalized, 'aucun modele ollama installe');
    }

    private function buildUrl(string $path): string
    {
        return rtrim($this->ollamaBaseUrl, '/') . $path;
    }
}
