<?php

namespace App\Service;

class EvenementVenueRecommendationService
{
    /**
     * @param array<string,mixed> $context
     * @return array{success: bool, message: string, lieux: array<int, array<string,mixed>>}
     */
    public function recommend(string $ville, string $type, ?int $capacity = null, array $context = []): array
    {
        $city = mb_strtolower(trim($ville));
        $eventType = mb_strtolower(trim($type));
        $mode = mb_strtolower(trim((string) ($context['mode'] ?? 'presentiel')));

        $baseVenues = $this->buildBaseVenues($city);
        if ($baseVenues === []) {
            return [
                'success' => false,
                'message' => 'Aucun lieu recommande pour cette ville pour le moment.',
                'lieux' => [],
            ];
        }

        $targetCapacity = max(20, (int) ($capacity ?? 80));
        $scored = [];

        foreach ($baseVenues as $index => $venue) {
            $capacityHint = (int) ($venue['capacity_hint'] ?? 80);
            $capacityGap = abs($targetCapacity - $capacityHint);

            $typeBonus = 0;
            if ($eventType !== '' && isset($venue['preferred_types']) && is_array($venue['preferred_types']) && in_array($eventType, $venue['preferred_types'], true)) {
                $typeBonus = 10;
            }

            $modeBonus = 0;
            if ($mode === 'hybride') {
                $modeBonus = 3;
            }

            $score = max(55, 92 - (int) round($capacityGap / 10) + $typeBonus + $modeBonus - ($index * 2));
            $similarity = sprintf('%s matchs type/capacite', max(3, 10 - $index));

            $scored[] = [
                'nom' => (string) ($venue['nom'] ?? 'Lieu recommande'),
                'adresse' => (string) ($venue['adresse'] ?? $ville),
                'distance_km' => (float) ($venue['distance_km'] ?? (1.0 + ($index * 1.3))),
                'score' => min(99, $score),
                'indice_evenements_similaires' => $similarity,
                'contacts' => [
                    'telephone' => (string) ($venue['telephone'] ?? ''),
                    'site' => (string) ($venue['site'] ?? ''),
                ],
            ];
        }

        usort($scored, static fn (array $a, array $b): int => ((int) $b['score']) <=> ((int) $a['score']));

        return [
            'success' => true,
            'message' => 'Lieux recommandes avec succes.',
            'lieux' => array_slice($scored, 0, 5),
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function buildBaseVenues(string $city): array
    {
        $catalog = [
            'tunis' => [
                ['nom' => 'Palais des Congres', 'adresse' => 'Avenue Mohamed V, Tunis', 'distance_km' => 2.1, 'capacity_hint' => 220, 'preferred_types' => ['formation', 'conference'], 'telephone' => '+216 71 100 200', 'site' => 'https://example.com/palais-congres'],
                ['nom' => 'Hotel du Lac - Salle Jasmin', 'adresse' => 'Centre-ville, Tunis', 'distance_km' => 1.4, 'capacity_hint' => 120, 'preferred_types' => ['atelier', 'formation'], 'telephone' => '+216 71 222 333', 'site' => 'https://example.com/hotel-lac'],
                ['nom' => 'Espace Cowork Lac 2', 'adresse' => 'Lac 2, Tunis', 'distance_km' => 4.8, 'capacity_hint' => 70, 'preferred_types' => ['atelier', 'networking'], 'telephone' => '+216 70 456 789', 'site' => 'https://example.com/cowork-lac2'],
            ],
            'sfax' => [
                ['nom' => 'Centre Culturel de Sfax', 'adresse' => 'Route de l Aeroport, Sfax', 'distance_km' => 3.2, 'capacity_hint' => 180, 'preferred_types' => ['conference', 'formation'], 'telephone' => '+216 74 100 200', 'site' => 'https://example.com/cc-sfax'],
                ['nom' => 'Hotel Les Oliviers', 'adresse' => 'Rue Majida Boulila, Sfax', 'distance_km' => 2.4, 'capacity_hint' => 90, 'preferred_types' => ['atelier', 'formation'], 'telephone' => '+216 74 300 400', 'site' => 'https://example.com/oliviers'],
            ],
            'sousse' => [
                ['nom' => 'Sousse Convention Hub', 'adresse' => 'Boulevard du 14 Janvier, Sousse', 'distance_km' => 2.9, 'capacity_hint' => 160, 'preferred_types' => ['conference', 'formation'], 'telephone' => '+216 73 500 600', 'site' => 'https://example.com/sousse-hub'],
                ['nom' => 'Marina Business Center', 'adresse' => 'Port El Kantaoui, Sousse', 'distance_km' => 5.1, 'capacity_hint' => 85, 'preferred_types' => ['atelier', 'networking'], 'telephone' => '+216 73 700 800', 'site' => 'https://example.com/marina-bc'],
            ],
        ];

        if (isset($catalog[$city])) {
            return $catalog[$city];
        }

        if ($city === '') {
            return [];
        }

        return [
            ['nom' => 'Maison de la Culture', 'adresse' => ucfirst($city), 'distance_km' => 2.0, 'capacity_hint' => 100, 'preferred_types' => ['formation', 'atelier'], 'telephone' => '', 'site' => ''],
            ['nom' => 'Business Center Central', 'adresse' => ucfirst($city), 'distance_km' => 3.5, 'capacity_hint' => 140, 'preferred_types' => ['conference', 'formation'], 'telephone' => '', 'site' => ''],
        ];
    }
}
