<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProductContentGenerator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{description:string,benefits:array<int,string>,usageTips:array<int,string>,seoKeywords:array<int,string>}
     */
    public function generate(array $data): array
    {
        $nom = trim((string) ($data['nom'] ?? ''));
        $categorie = trim((string) ($data['categorie'] ?? ''));
        $marque = trim((string) ($data['marque'] ?? ''));
        $prix = isset($data['prix']) ? (string) $data['prix'] : '';

        if ($nom === '') {
            throw new \InvalidArgumentException('Le nom du produit est obligatoire.');
        }

        $apiKey = (string) ($_ENV['PRODUCT_AI_API_KEY'] ?? getenv('PRODUCT_AI_API_KEY') ?: '');
        $model = (string) ($_ENV['PRODUCT_AI_MODEL'] ?? getenv('PRODUCT_AI_MODEL') ?: 'gpt-4o-mini');
        $baseUrl = rtrim((string) ($_ENV['PRODUCT_AI_BASE_URL'] ?? getenv('PRODUCT_AI_BASE_URL') ?: 'https://api.openai.com'), '/');

        if ($apiKey === '') {
            return $this->fallbackContent($nom, $categorie, $marque);
        }

        $prompt = sprintf(
            "Produit: %s\nCategorie: %s\nMarque: %s\nPrix: %s\n\nRetourne UNIQUEMENT un JSON avec les cles: description (string), benefits (array de 3), usageTips (array de 3), seoKeywords (array de 8).",
            $nom,
            $categorie !== '' ? $categorie : 'non precisee',
            $marque !== '' ? $marque : 'non precisee',
            $prix !== '' ? $prix : 'non precise'
        );

        try {
            $response = $this->httpClient->request('POST', $baseUrl . '/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.5,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es expert e-commerce parapharmacie. Reponse en francais, concise, non medicale.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ],
            ]);

            $payload = $response->toArray(false);
            $content = (string) ($payload['choices'][0]['message']['content'] ?? '');
            $decoded = json_decode($content, true);

            if (!is_array($decoded)) {
                return $this->fallbackContent($nom, $categorie, $marque);
            }

            return $this->normalize($decoded, $nom, $categorie, $marque);
        } catch (\Throwable) {
            return $this->fallbackContent($nom, $categorie, $marque);
        }
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array{description:string,benefits:array<int,string>,usageTips:array<int,string>,seoKeywords:array<int,string>}
     */
    private function normalize(array $decoded, string $nom, string $categorie, string $marque): array
    {
        $description = trim((string) ($decoded['description'] ?? ''));
        $benefits = $this->normalizeList($decoded['benefits'] ?? []);
        $usageTips = $this->normalizeList($decoded['usageTips'] ?? []);
        $seoKeywords = $this->normalizeList($decoded['seoKeywords'] ?? []);

        if ($description === '') {
            return $this->fallbackContent($nom, $categorie, $marque);
        }

        return [
            'description' => $description,
            'benefits' => array_slice($benefits, 0, 3),
            'usageTips' => array_slice($usageTips, 0, 3),
            'seoKeywords' => array_slice($seoKeywords, 0, 8),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return array{description:string,benefits:array<int,string>,usageTips:array<int,string>,seoKeywords:array<int,string>}
     */
    private function fallbackContent(string $nom, string $categorie, string $marque): array
    {
        $brandPrefix = $marque !== '' ? $marque . ' - ' : '';
        $categoryText = $categorie !== '' ? str_replace('_', ' ', $categorie) : 'parapharmacie';

        return [
            'description' => sprintf(
                '%s%s est un produit de %s pense pour un usage regulier. Sa formule est adaptee a une routine quotidienne et offre une experience confortable.',
                $brandPrefix,
                $nom,
                $categoryText
            ),
            'benefits' => [
                'Aide a maintenir une routine de soin simple et efficace.',
                'Texture agreable et application facile au quotidien.',
                'Compatible avec une utilisation reguliere selon les besoins.',
            ],
            'usageTips' => [
                'Appliquer sur peau propre selon les recommandations du produit.',
                'Respecter la frequence d utilisation indiquee sur l emballage.',
                'Conserver dans un endroit sec, a l abri de la chaleur.',
            ],
            'seoKeywords' => [
                $nom,
                $marque !== '' ? $marque : 'parapharmacie',
                $categoryText,
                'soin quotidien',
                'produit parapharmacie',
                'achat en ligne',
                'conseil utilisation',
                'qualite',
            ],
        ];
    }
}
