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
     * @return array{description: string, benefits: array<int, string>, usageTips: array<int, string>, seoKeywords: array<int, string>}
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
     * @return array{description: string, benefits: array<int, string>, usageTips: array<int, string>, seoKeywords: array<int, string>}
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

        $content = [
            'description' => $description,
            'benefits' => array_slice($benefits, 0, 3),
            'usageTips' => array_slice($usageTips, 0, 3),
            'seoKeywords' => array_slice($seoKeywords, 0, 8),
        ];

        return $this->applyCategoryFeedback($content, $categorie, $nom, $marque);
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
     * @return array{description: string, benefits: array<int, string>, usageTips: array<int, string>, seoKeywords: array<int, string>}
     */
    private function fallbackContent(string $nom, string $categorie, string $marque): array
    {
        $brandPrefix = $marque !== '' ? $marque . ' - ' : '';
        $categoryText = $categorie !== '' ? str_replace('_', ' ', $categorie) : 'parapharmacie';

        $content = [
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

        return $this->applyCategoryFeedback($content, $categorie, $nom, $marque);
    }

    /**
     * @param array{description:string,benefits:array<int,string>,usageTips:array<int,string>,seoKeywords:array<int,string>} $content
     * @return array{description:string,benefits:array<int,string>,usageTips:array<int,string>,seoKeywords:array<int,string>}
     */
    private function applyCategoryFeedback(array $content, string $categorie, string $nom, string $marque): array
    {
        $feedback = $this->categoryFeedback($categorie);
        if ($feedback === null) {
            return $content;
        }

        $description = trim((string) $content['description']);
        if ($description !== '' && !str_contains(mb_strtolower($description, 'UTF-8'), mb_strtolower($feedback['descriptionHint'], 'UTF-8'))) {
            $description .= ' ' . $feedback['descriptionHint'];
        }

        $benefits = array_slice(array_values(array_unique(array_merge(
            $this->normalizeList($content['benefits']),
            $feedback['benefits']
        ))), 0, 3);

        $usageTips = array_slice(array_values(array_unique(array_merge(
            $this->normalizeList($content['usageTips']),
            $feedback['usageTips']
        ))), 0, 3);

        $seoKeywords = array_slice(array_values(array_unique(array_merge(
            $this->normalizeList($content['seoKeywords']),
            [
                $nom,
                $marque !== '' ? $marque : 'parapharmacie',
                $feedback['seoKeyword'],
                str_replace('_', ' ', $categorie),
            ]
        ))), 0, 8);

        return [
            'description' => trim($description),
            'benefits' => $benefits,
            'usageTips' => $usageTips,
            'seoKeywords' => $seoKeywords,
        ];
    }

    /**
     * @return array{descriptionHint:string,benefits:array<int,string>,usageTips:array<int,string>,seoKeyword:string}|null
     */
    private function categoryFeedback(string $categorie): ?array
    {
        return match ($categorie) {
            'soins_visage' => [
                'descriptionHint' => 'Convient a une routine visage quotidienne avec un confort cutane durable.',
                'benefits' => ['Aide a maintenir l hydratation du visage.'],
                'usageTips' => ['Appliquer sur le visage propre, matin et soir.'],
                'seoKeyword' => 'soin visage',
            ],
            'soins_corps' => [
                'descriptionHint' => 'Ideale pour le soin quotidien du corps et le confort de la peau.',
                'benefits' => ['Contribue a une peau plus souple au quotidien.'],
                'usageTips' => ['Masser sur les zones seches jusqu a absorption.'],
                'seoKeyword' => 'soin corps',
            ],
            'soins_capillaires' => [
                'descriptionHint' => 'Pensee pour une routine capillaire simple et reguliere.',
                'benefits' => ['Aide a garder des cheveux doux et faciles a coiffer.'],
                'usageTips' => ['Appliquer sur cheveux mouilles puis rincer si necessaire.'],
                'seoKeyword' => 'soin capillaire',
            ],
            'hygiene' => [
                'descriptionHint' => 'Adaptee aux gestes d hygiene de tous les jours.',
                'benefits' => ['Participe a une sensation de proprete durable.'],
                'usageTips' => ['Utiliser selon les besoins dans la routine quotidienne.'],
                'seoKeyword' => 'hygiene quotidienne',
            ],
            'nutrition', 'complements' => [
                'descriptionHint' => 'S integre facilement a une routine nutritionnelle reguliere.',
                'benefits' => ['Complete une routine bien-etre et equilibree.'],
                'usageTips' => ['Respecter la dose recommandee sur l emballage.'],
                'seoKeyword' => 'complement alimentaire',
            ],
            'minceur' => [
                'descriptionHint' => 'Peut accompagner une routine minceur avec regularite.',
                'benefits' => ['S inscrit dans une routine lifestyle active.'],
                'usageTips' => ['Associer a une hygiene de vie equilibree.'],
                'seoKeyword' => 'routine minceur',
            ],
            'bebe' => [
                'descriptionHint' => 'Formule concue pour une routine bebe avec douceur.',
                'benefits' => ['Texture douce adaptee a un usage frequent.'],
                'usageTips' => ['Appliquer delicatement sur peau propre et seche.'],
                'seoKeyword' => 'soin bebe',
            ],
            'materiel' => [
                'descriptionHint' => 'Concu pour un usage pratique dans le suivi quotidien.',
                'benefits' => ['Facilite les gestes de sante du quotidien.'],
                'usageTips' => ['Lire la notice avant la premiere utilisation.'],
                'seoKeyword' => 'materiel medical',
            ],
            default => null,
        };
    }
}
