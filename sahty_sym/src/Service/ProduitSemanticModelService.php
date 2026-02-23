<?php

namespace App\Service;

use App\Entity\Produit;
use App\Repository\ProduitRepository;

class ProduitSemanticModelService
{
    public function __construct(
        private readonly ProduitRepository $produitRepository
    ) {
    }

    /**
     * @param string[] $semanticKeywords
     * @return Produit[]
     */
    public function search(string $rawQuery, array $semanticKeywords = [], int $limit = 30, float $minScore = 0.08): array
    {
        $products = array_values(array_filter(
            $this->produitRepository->findAll(),
            static fn (Produit $p): bool => $p->isEstActif() !== false
        ));

        if (empty($products)) {
            return [];
        }

        $queryTerms = $this->buildQueryTerms($rawQuery, $semanticKeywords);
        if (empty($queryTerms)) {
            return [];
        }

        $documents = [];
        $df = [];
        foreach ($products as $product) {
            $terms = $this->buildProductTerms($product);
            if (empty($terms)) {
                continue;
            }

            $documents[] = ['product' => $product, 'terms' => $terms];

            foreach (array_unique(array_keys($terms)) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }

        if (empty($documents)) {
            return [];
        }

        $vocabulary = array_values(array_unique(array_merge(array_keys($queryTerms), array_keys($df))));
        $docCount = count($documents);

        $idf = [];
        foreach ($vocabulary as $term) {
            $idf[$term] = log(($docCount + 1) / (($df[$term] ?? 0) + 1)) + 1.0;
        }

        $queryVector = [];
        foreach ($queryTerms as $term => $tf) {
            if (!isset($idf[$term])) {
                continue;
            }

            $queryVector[$term] = (1.0 + log($tf)) * $idf[$term];
        }

        if (empty($queryVector)) {
            return [];
        }

        $queryNorm = $this->vectorNorm($queryVector);
        if ($queryNorm <= 0.0) {
            return [];
        }

        $normalizedRawQuery = $this->normalizeText($rawQuery);
        $scored = [];

        foreach ($documents as $entry) {
            /** @var Produit $product */
            $product = $entry['product'];
            /** @var array<string, int> $productTerms */
            $productTerms = $entry['terms'];

            $docVector = [];
            foreach ($productTerms as $term => $tf) {
                if (!isset($queryVector[$term])) {
                    continue;
                }

                $docVector[$term] = (1.0 + log($tf)) * ($idf[$term] ?? 1.0);
            }

            if (empty($docVector)) {
                continue;
            }

            $docNorm = $this->vectorNorm($docVector);
            if ($docNorm <= 0.0) {
                continue;
            }

            $dot = 0.0;
            foreach ($docVector as $term => $weight) {
                $dot += $weight * ($queryVector[$term] ?? 0.0);
            }

            $score = $dot / ($queryNorm * $docNorm);
            $score += $this->exactMatchBoost($product, $normalizedRawQuery);

            if ($score >= $minScore) {
                $scored[] = ['product' => $product, 'score' => $score];
            }
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return array_map(
            static fn (array $item): Produit => $item['product'],
            array_slice($scored, 0, max(1, $limit))
        );
    }

    /**
     * @param string[] $semanticKeywords
     * @return array<string, int>
     */
    private function buildQueryTerms(string $rawQuery, array $semanticKeywords): array
    {
        $terms = [];
        $chunks = array_merge([$rawQuery], $semanticKeywords);

        foreach ($chunks as $chunk) {
            foreach ($this->tokenize($chunk) as $token) {
                $terms[$token] = ($terms[$token] ?? 0) + 1;
            }
        }

        return $terms;
    }

    /**
     * @return array<string, int>
     */
    private function buildProductTerms(Produit $product): array
    {
        $terms = [];

        $this->addWeightedTokens($terms, (string) $product->getNom(), 4);
        $this->addWeightedTokens($terms, (string) $product->getCategorie(), 3);
        $this->addWeightedTokens($terms, (string) $product->getMarque(), 2);
        $this->addWeightedTokens($terms, (string) $product->getDescription(), 1);

        return $terms;
    }

    /**
     * @param array<string, int> $bag
     */
    private function addWeightedTokens(array &$bag, string $text, int $weight): void
    {
        if ($weight <= 0) {
            return;
        }

        foreach ($this->tokenize($text) as $token) {
            $bag[$token] = ($bag[$token] ?? 0) + $weight;
        }
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return [];
        }

        $rawTokens = preg_split('/\s+/', $normalized) ?: [];
        $stopWords = $this->getStopWords();
        $tokens = [];

        foreach ($rawTokens as $token) {
            if (mb_strlen($token) < 3 || in_array($token, $stopWords, true)) {
                continue;
            }

            $tokens[] = $token;
        }

        $count = count($tokens);
        if ($count >= 2) {
            for ($i = 0; $i < $count - 1; $i++) {
                $tokens[] = $tokens[$i] . '_' . $tokens[$i + 1];
            }
        }

        return $tokens;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, float> $vector
     */
    private function vectorNorm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $weight) {
            $sum += $weight * $weight;
        }

        return sqrt($sum);
    }

    private function exactMatchBoost(Produit $product, string $normalizedRawQuery): float
    {
        if ($normalizedRawQuery === '') {
            return 0.0;
        }

        $name = $this->normalizeText((string) $product->getNom());
        $category = $this->normalizeText((string) $product->getCategorie());

        if ($name !== '' && str_contains($name, $normalizedRawQuery)) {
            return 0.25;
        }

        if ($category !== '' && str_contains($category, $normalizedRawQuery)) {
            return 0.12;
        }

        return 0.0;
    }

    /**
     * @return string[]
     */
    private function getStopWords(): array
    {
        return [
            'a', 'au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du', 'en', 'et',
            'je', 'la', 'le', 'les', 'mon', 'mes', 'ma', 'moi', 'pour', 'par', 'pas', 'qui',
            'que', 'sur', 'un', 'une', 'vos', 'votre', 'produit', 'produits', 'parapharmacie',
            'pharmacie', 'besoin', 'symptome',
        ];
    }
}

