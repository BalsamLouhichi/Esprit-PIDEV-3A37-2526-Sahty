<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class EvenementVenueRecommendationService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';
    private const LOCAL_CITY_COORDS = [
        'tunis' => ['lat' => 36.8065, 'lon' => 10.1815, 'display_name' => 'Tunis, Tunisie'],
        'ariana' => ['lat' => 36.8625, 'lon' => 10.1956, 'display_name' => 'Ariana, Tunisie'],
        'ben arous' => ['lat' => 36.7531, 'lon' => 10.2189, 'display_name' => 'Ben Arous, Tunisie'],
        'la manouba' => ['lat' => 36.81, 'lon' => 10.095, 'display_name' => 'La Manouba, Tunisie'],
        'nabeul' => ['lat' => 36.4561, 'lon' => 10.7376, 'display_name' => 'Nabeul, Tunisie'],
        'sousse' => ['lat' => 35.8256, 'lon' => 10.6411, 'display_name' => 'Sousse, Tunisie'],
        'sfax' => ['lat' => 34.7406, 'lon' => 10.7603, 'display_name' => 'Sfax, Tunisie'],
        'monastir' => ['lat' => 35.7643, 'lon' => 10.8113, 'display_name' => 'Monastir, Tunisie'],
        'mahdia' => ['lat' => 35.5047, 'lon' => 11.0622, 'display_name' => 'Mahdia, Tunisie'],
        'bizerte' => ['lat' => 37.2744, 'lon' => 9.8739, 'display_name' => 'Bizerte, Tunisie'],
        'beja' => ['lat' => 36.7333, 'lon' => 9.1833, 'display_name' => 'Beja, Tunisie'],
        'jendouba' => ['lat' => 36.5011, 'lon' => 8.7802, 'display_name' => 'Jendouba, Tunisie'],
        'kairouan' => ['lat' => 35.6781, 'lon' => 10.0963, 'display_name' => 'Kairouan, Tunisie'],
        'gabes' => ['lat' => 33.8815, 'lon' => 10.0982, 'display_name' => 'Gabes, Tunisie'],
        'medenine' => ['lat' => 33.3549, 'lon' => 10.5055, 'display_name' => 'Medenine, Tunisie'],
        'gafsa' => ['lat' => 34.425, 'lon' => 8.7842, 'display_name' => 'Gafsa, Tunisie'],
        'tozeur' => ['lat' => 33.9197, 'lon' => 8.1335, 'display_name' => 'Tozeur, Tunisie'],
        'kasserine' => ['lat' => 35.1676, 'lon' => 8.8365, 'display_name' => 'Kasserine, Tunisie'],
        'sidi bouzid' => ['lat' => 35.0382, 'lon' => 9.4858, 'display_name' => 'Sidi Bouzid, Tunisie'],
        'kef' => ['lat' => 36.1826, 'lon' => 8.7148, 'display_name' => 'Le Kef, Tunisie'],
        'zaghouan' => ['lat' => 36.4029, 'lon' => 10.1429, 'display_name' => 'Zaghouan, Tunisie'],
        'siliana' => ['lat' => 36.0844, 'lon' => 9.3708, 'display_name' => 'Siliana, Tunisie'],
        'tataouine' => ['lat' => 32.93, 'lon' => 10.45, 'display_name' => 'Tataouine, Tunisie'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function recommend(string $city, string $eventType, ?int $capacity = null, array $context = []): array
    {
        $city = trim($city);
        if ($city === '') {
            return [
                'success' => false,
                'message' => 'La ville est obligatoire pour recommander des lieux.',
                'lieux' => [],
            ];
        }

        $geo = $this->geocodeCity($city);
        if ($geo === null) {
            return [
                'success' => false,
                'message' => 'Impossible de localiser cette ville.',
                'lieux' => [],
            ];
        }

        $elements = $this->fetchNearbyPlaces((float) $geo['lat'], (float) $geo['lon'], $eventType, false);
        $usedFallback = false;
        if (count($elements) === 0) {
            $elements = $this->fetchNearbyPlaces((float) $geo['lat'], (float) $geo['lon'], $eventType, true);
            $usedFallback = true;
        }

        $normalized = [];

        foreach ($elements as $element) {
            $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
            $name = trim((string) ($tags['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
            $lon = $element['lon'] ?? ($element['center']['lon'] ?? null);
            if (!is_numeric($lat) || !is_numeric($lon)) {
                continue;
            }

            $score = $this->scorePlace($tags, $eventType, $capacity, $context);
            $normalized[] = [
                'nom' => $name,
                'categorie' => $this->detectCategory($tags),
                'adresse' => $this->buildAddress($tags),
                'distance_km' => round($this->distanceKm((float) $geo['lat'], (float) $geo['lon'], (float) $lat, (float) $lon), 2),
                'score' => $score,
                'raison' => $this->buildReason($tags, $eventType, $capacity, $context),
                'contacts' => $this->extractContacts($tags),
                'indice_evenements_similaires' => $this->buildSimilarityHint($tags, $eventType, $context),
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return $a['distance_km'] <=> $b['distance_km'];
            }

            return $b['score'] <=> $a['score'];
        });

        return [
            'success' => true,
            'message' => count($normalized) > 0
                ? ($usedFallback
                    ? 'Lieux recommandes generes avec succes (mode elargi active).'
                    : 'Lieux recommandes generes avec succes.')
                : ($usedFallback
                    ? 'Aucun lieu pertinent trouve, meme apres elargissement de la recherche.'
                    : 'Aucun lieu pertinent trouve dans cette zone.'),
            'ville' => (string) ($geo['display_name'] ?? $city),
            'lieux' => array_slice($normalized, 0, 10),
            'fallback_used' => $usedFallback,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function geocodeCity(string $city): ?array
    {
        $queries = $this->buildGeocodingQueries($city);

        foreach ($queries as $query) {
            $result = $this->queryNominatim($query);
            if ($result !== null) {
                return $result;
            }
        }

        return $this->fallbackLocalCity($city);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function queryNominatim(string $query): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::NOMINATIM_URL, [
                'query' => [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 1,
                ],
                'headers' => [
                    'User-Agent' => 'SahtyEventModule/1.0',
                ],
                'timeout' => 8,
            ]);

            $results = $response->toArray(false);
            if (count($results) === 0) {
                return null;
            }

            $first = $results[0];
            if (!isset($first['lat'], $first['lon'])) {
                return null;
            }

            return $first;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    private function buildGeocodingQueries(string $city): array
    {
        $base = trim($city);
        $ascii = $this->normalizeText($base);
        $queries = [$base];

        if ($ascii !== '' && strcasecmp($ascii, $base) !== 0) {
            $queries[] = $ascii;
        }

        $queries[] = $base . ', Tunisie';
        if ($ascii !== '') {
            $queries[] = $ascii . ', Tunisie';
        }
        $queries[] = $base . ', Tunisia';
        if ($ascii !== '') {
            $queries[] = $ascii . ', Tunisia';
        }

        return array_values(array_unique(array_filter($queries, static fn (string $v): bool => trim($v) !== '')));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fallbackLocalCity(string $city): ?array
    {
        $normalized = $this->normalizeText($city);
        if ($normalized === '') {
            return null;
        }

        if (isset(self::LOCAL_CITY_COORDS[$normalized])) {
            return self::LOCAL_CITY_COORDS[$normalized];
        }

        foreach (self::LOCAL_CITY_COORDS as $name => $coords) {
            if (str_contains($name, $normalized) || str_contains($normalized, $name)) {
                return $coords;
            }
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'å', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', '’', '\''],
            ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', ' ', ' '],
            $value
        );

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchNearbyPlaces(float $lat, float $lon, string $eventType, bool $broadSearch = false): array
    {
        $radius = $broadSearch ? 12000 : 7000;
        $amenities = $broadSearch
            ? $this->mapFallbackAmenities($eventType)
            : $this->mapTypeToAmenities($eventType);

        $fragments = [];
        foreach ($amenities as $amenity) {
            $fragments[] = sprintf('nwr(around:%d,%s,%s)["amenity"="%s"];', $radius, $lat, $lon, $amenity);
        }
        $fragments[] = sprintf('nwr(around:%d,%s,%s)["tourism"="hotel"];', $radius, $lat, $lon);

        $query = "[out:json][timeout:25];(" . implode('', $fragments) . ");out center 25;";

        try {
            $response = $this->httpClient->request('POST', self::OVERPASS_URL, [
                'body' => ['data' => $query],
                'timeout' => 20,
            ]);

            $payload = $response->toArray(false);
            $elements = $payload['elements'] ?? [];
            if (!is_array($elements)) {
                return [];
            }

            $normalized = [];
            foreach ($elements as $element) {
                if (is_array($element)) {
                    $normalized[] = $element;
                }
            }

            return $normalized;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function mapTypeToAmenities(string $eventType): array
    {
        $type = mb_strtolower(trim($eventType));

        return match ($type) {
            'atelier' => ['community_centre', 'school', 'college', 'university'],
            'depistage' => ['hospital', 'clinic', 'community_centre'],
            'conference', 'formation' => ['conference_centre', 'university', 'college', 'community_centre'],
            'webinaire' => ['university', 'college', 'community_centre'],
            default => ['conference_centre', 'community_centre', 'university', 'college'],
        };
    }

    /**
     * @return list<string>
     */
    private function mapFallbackAmenities(string $eventType): array
    {
        $base = $this->mapTypeToAmenities($eventType);
        $fallback = [
            'community_centre',
            'townhall',
            'theatre',
            'arts_centre',
            'social_centre',
            'library',
            'school',
            'college',
            'university',
        ];

        return array_values(array_unique(array_merge($base, $fallback)));
    }

    /**
     * @param array<string, mixed> $tags
     * @param array<string, mixed> $context
     */
    private function scorePlace(array $tags, string $eventType, ?int $capacity, array $context): int
    {
        $score = 50;
        $amenity = mb_strtolower((string) ($tags['amenity'] ?? ''));
        $name = mb_strtolower((string) ($tags['name'] ?? ''));

        foreach ($this->mapTypeToAmenities($eventType) as $expected) {
            if ($amenity === $expected) {
                $score += 25;
                break;
            }
        }

        if (str_contains($name, 'centre') || str_contains($name, 'conference')) {
            $score += 8;
        }

        if (isset($tags['phone']) || isset($tags['contact:phone'])) {
            $score += 8;
        }
        if (isset($tags['website']) || isset($tags['contact:website'])) {
            $score += 5;
        }

        if ($capacity !== null && $capacity > 0) {
            $tagCapacity = (int) preg_replace('/[^0-9]/', '', (string) ($tags['capacity'] ?? '0'));
            if ($tagCapacity > 0) {
                if ($tagCapacity >= $capacity) {
                    $score += 10;
                } else {
                    $score -= 10;
                }
            }
        }

        $text = mb_strtolower(trim((string) ($context['title'] ?? '') . ' ' . (string) ($context['description'] ?? '')));
        if ($text !== '') {
            $medicalKeywords = ['sante', 'medical', 'clinique', 'depistage', 'cardio', 'diabete', 'prevention', 'patient'];
            $trainingKeywords = ['atelier', 'formation', 'workshop', 'pratique', 'simulation'];

            foreach ($medicalKeywords as $keyword) {
                if (str_contains($text, $keyword) && in_array($amenity, ['hospital', 'clinic', 'community_centre', 'university'], true)) {
                    $score += 6;
                    break;
                }
            }
            foreach ($trainingKeywords as $keyword) {
                if (str_contains($text, $keyword) && in_array($amenity, ['university', 'college', 'school', 'community_centre'], true)) {
                    $score += 5;
                    break;
                }
            }
        }

        $mode = mb_strtolower((string) ($context['mode'] ?? ''));
        if ($mode === 'hybride' && (isset($tags['internet_access']) || isset($tags['wifi']) || str_contains($name, 'conference'))) {
            $score += 7;
        }

        $budget = is_numeric($context['budget'] ?? null) ? (float) $context['budget'] : null;
        if ($budget !== null) {
            if ($budget <= 0 && in_array($amenity, ['community_centre', 'university', 'school', 'college'], true)) {
                $score += 4;
            }
            if ($budget > 30 && ((string) ($tags['tourism'] ?? '') === 'hotel' || str_contains($name, 'hotel') || str_contains($name, 'business'))) {
                $score += 4;
            }
        }

        $durationHours = is_numeric($context['duration_hours'] ?? null) ? (float) $context['duration_hours'] : null;
        if ($durationHours !== null && $durationHours >= 6 && in_array($amenity, ['conference_centre', 'community_centre', 'university'], true)) {
            $score += 3;
        }

        return max(1, min(100, $score));
    }

    /**
     * @param array<string, mixed> $tags
     */
    private function detectCategory(array $tags): string
    {
        $amenity = (string) ($tags['amenity'] ?? '');
        $tourism = (string) ($tags['tourism'] ?? '');

        if ($amenity !== '') {
            return $amenity;
        }
        if ($tourism !== '') {
            return $tourism;
        }

        return 'lieu';
    }

    /**
     * @param array<string, mixed> $tags
     */
    private function buildAddress(array $tags): string
    {
        $parts = [];
        $street = trim((string) ($tags['addr:street'] ?? ''));
        $number = trim((string) ($tags['addr:housenumber'] ?? ''));
        $city = trim((string) ($tags['addr:city'] ?? ''));

        if ($street !== '') {
            $parts[] = trim($number . ' ' . $street);
        }
        if ($city !== '') {
            $parts[] = $city;
        }

        return count($parts) > 0 ? implode(', ', $parts) : 'Adresse non disponible';
    }

    /**
     * @param array<string, mixed> $tags
     * @return array{telephone: string, email: string, site: string}
     */
    private function extractContacts(array $tags): array
    {
        return [
            'telephone' => (string) ($tags['contact:phone'] ?? $tags['phone'] ?? ''),
            'email' => (string) ($tags['contact:email'] ?? $tags['email'] ?? ''),
            'site' => (string) ($tags['contact:website'] ?? $tags['website'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $tags
     * @param array<string, mixed> $context
     */
    private function buildReason(array $tags, string $eventType, ?int $capacity, array $context): string
    {
        $reasons = [];
        $amenity = (string) ($tags['amenity'] ?? '');

        if ($amenity !== '') {
            $reasons[] = sprintf('Type de lieu compatible (%s)', $amenity);
        }
        if (isset($tags['phone']) || isset($tags['contact:phone'])) {
            $reasons[] = 'Contact telephonique disponible';
        }
        if (isset($tags['website']) || isset($tags['contact:website'])) {
            $reasons[] = 'Site web disponible';
        }
        if ($capacity !== null && $capacity > 0 && isset($tags['capacity'])) {
            $reasons[] = sprintf('Capacite declaree: %s', (string) $tags['capacity']);
        }
        if (mb_strtolower((string) ($context['mode'] ?? '')) === 'hybride') {
            $reasons[] = 'Compatible avec un format hybride (sur place + en ligne)';
        }

        if (count($reasons) === 0) {
            $reasons[] = sprintf('Lieu recommande pour un evenement de type "%s"', $eventType);
        }

        return implode(' | ', $reasons);
    }

    /**
     * @param array<string, mixed> $tags
     * @param array<string, mixed> $context
     */
    private function buildSimilarityHint(array $tags, string $eventType, array $context): string
    {
        $name = mb_strtolower((string) ($tags['name'] ?? ''));
        $eventType = mb_strtolower($eventType);

        if (str_contains($name, 'congr') || str_contains($name, 'conference')) {
            return 'Indice fort: ce lieu semble accueillir des evenements similaires.';
        }
        if (isset($tags['operator']) || isset($tags['brand'])) {
            return 'Indice moyen: etablissement structure, souvent utilise pour des evenements.';
        }
        if (!empty($context['title']) || !empty($context['description'])) {
            return 'Indice contextuel: correspondance enrichie selon votre titre, description et format.';
        }

        return sprintf('Indice contextuel: lieu compatible avec le format "%s".', $eventType);
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
