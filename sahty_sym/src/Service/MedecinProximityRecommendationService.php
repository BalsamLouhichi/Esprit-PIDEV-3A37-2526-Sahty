<?php

namespace App\Service;

use App\Entity\Medecin;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MedecinProximityRecommendationService
{
    private const GEOCODER_ENDPOINT = 'https://nominatim.openstreetmap.org/search';
    private const ENABLE_REMOTE_GEOCODING = false;
    /** @var array<string, array{lat: float, lng: float}> */
    private const TUNISIA_CITY_COORDINATES = [
        'tunis' => ['lat' => 36.8065, 'lng' => 10.1815],
        'ariana' => ['lat' => 36.8665, 'lng' => 10.1647],
        'la soukra' => ['lat' => 36.8776, 'lng' => 10.2516],
        'raoued' => ['lat' => 36.9341, 'lng' => 10.2851],
        'kalaa el andalous' => ['lat' => 37.0629, 'lng' => 10.1187],
        'la marsa' => ['lat' => 36.8782, 'lng' => 10.3247],
        'carthage' => ['lat' => 36.8529, 'lng' => 10.3230],
        'ben arous' => ['lat' => 36.7531, 'lng' => 10.2189],
        'manouba' => ['lat' => 36.8080, 'lng' => 10.0963],
        'nabeul' => ['lat' => 36.4561, 'lng' => 10.7376],
        'hammamet' => ['lat' => 36.4000, 'lng' => 10.6167],
        'bizerte' => ['lat' => 37.2744, 'lng' => 9.8739],
        'beja' => ['lat' => 36.7256, 'lng' => 9.1817],
        'jendouba' => ['lat' => 36.5011, 'lng' => 8.7803],
        'kef' => ['lat' => 36.1742, 'lng' => 8.7049],
        'siliana' => ['lat' => 36.0849, 'lng' => 9.3708],
        'zaghouan' => ['lat' => 36.4029, 'lng' => 10.1430],
        'sousse' => ['lat' => 35.8256, 'lng' => 10.6360],
        'monastir' => ['lat' => 35.7643, 'lng' => 10.8113],
        'mahdia' => ['lat' => 35.5047, 'lng' => 11.0622],
        'kairouan' => ['lat' => 35.6781, 'lng' => 10.0963],
        'sfax' => ['lat' => 34.7406, 'lng' => 10.7603],
        'gabes' => ['lat' => 33.8815, 'lng' => 10.0982],
        'medenine' => ['lat' => 33.3549, 'lng' => 10.5055],
        'djerba' => ['lat' => 33.8076, 'lng' => 10.8451],
        'houmt souk' => ['lat' => 33.8758, 'lng' => 10.8575],
        'zarzis' => ['lat' => 33.5039, 'lng' => 11.1122],
        'tataouine' => ['lat' => 32.9297, 'lng' => 10.4518],
        'gafsa' => ['lat' => 34.4250, 'lng' => 8.7842],
        'tozeur' => ['lat' => 33.9197, 'lng' => 8.1335],
        'kebili' => ['lat' => 33.7044, 'lng' => 8.9690],
        'sidi bouzid' => ['lat' => 35.0382, 'lng' => 9.4849],
        'kasserine' => ['lat' => 35.1676, 'lng' => 8.8365],
    ];

    /** @var array<string, array{lat: float, lng: float}|null> */
    private array $geocodeCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param Medecin[] $medecins
     * @return array<int, array<string, mixed>>
     */
    public function recommendNearest(
        array $medecins,
        float $patientLat,
        float $patientLng,
        int $limit = 5,
        ?float $maxDistanceKm = null
    ): array
    {
        // Evite des requetes geocoding trop longues quand la base contient beaucoup de medecins.
        $medecins = array_slice($medecins, 0, 20);
        /** @var array<int, array<string, mixed>> $resultsByMedecinId */
        $resultsByMedecinId = [];
        $candidates = [];

        foreach ($medecins as $medecin) {
            if (!$medecin instanceof Medecin || !$medecin->isEstActif()) {
                continue;
            }

            $adresseCabinet = trim((string) $medecin->getAdresseCabinet());
            $ville = trim((string) $medecin->getVille());
            if ($adresseCabinet === '' && $ville === '') {
                continue;
            }

            $queries = $this->buildAddressQueries($medecin);
            $candidates[] = [
                'medecin' => $medecin,
                'queries' => $queries,
            ];

            $cityCoords = $this->resolveLocalCityCoordinates($ville);
            if ($cityCoords !== null) {
                $resultsByMedecinId[$medecin->getId()] = $this->buildRecommendationResult(
                    $medecin,
                    $patientLat,
                    $patientLng,
                    $cityCoords
                );
            }
        }

        $results = array_values($resultsByMedecinId);
        if ($maxDistanceKm !== null) {
            $results = array_values(array_filter(
                $results,
                static fn (array $item): bool => (float) $item['distance_km'] <= $maxDistanceKm
            ));
        }
        usort(
            $results,
            static fn (array $a, array $b): int => ($a['distance_km'] <=> $b['distance_km'])
        );
        if ($results !== [] || !self::ENABLE_REMOTE_GEOCODING) {
            return array_slice($results, 0, max(1, $limit));
        }

        $addressQueries = [];
        foreach ($candidates as $item) {
            foreach ($item['queries'] as $query) {
                if (is_string($query) && $query !== '') {
                    $addressQueries[] = $query;
                }
            }
        }
        $addressQueries = array_values(array_unique(array_filter($addressQueries)));
        $coordsByAddress = $this->geocodeAddresses($addressQueries);

        foreach ($candidates as $item) {
            /** @var Medecin $medecin */
            $medecin = $item['medecin'];
            $coords = null;
            foreach ($item['queries'] as $query) {
                $coords = $coordsByAddress[$query] ?? null;
                if ($coords !== null) {
                    break;
                }
            }
            if ($coords === null) {
                continue;
            }

            $resultsByMedecinId[$medecin->getId()] = $this->buildRecommendationResult(
                $medecin,
                $patientLat,
                $patientLng,
                $coords
            );
        }

        $results = array_values($resultsByMedecinId);
        if ($maxDistanceKm !== null) {
            $results = array_values(array_filter(
                $results,
                static fn (array $item): bool => (float) $item['distance_km'] <= $maxDistanceKm
            ));
        }
        usort(
            $results,
            static fn (array $a, array $b): int => ($a['distance_km'] <=> $b['distance_km'])
        );

        return array_slice($results, 0, max(1, $limit));
    }

    /**
     * @param array<int, string> $addresses
     * @return array<string, array{lat: float, lng: float}|null>
     */
    private function geocodeAddresses(array $addresses): array
    {
        $results = [];
        $responses = [];
        $responseAddressMap = [];

        try {
            foreach ($addresses as $address) {
                if (array_key_exists($address, $this->geocodeCache)) {
                    $results[$address] = $this->geocodeCache[$address];
                    continue;
                }

                $response = $this->httpClient->request('GET', self::GEOCODER_ENDPOINT, [
                    'query' => [
                        'q' => $address,
                        'format' => 'jsonv2',
                        'limit' => 1,
                        'countrycodes' => 'tn',
                    ],
                    'headers' => [
                        'User-Agent' => 'SahtySym/1.0 (doctor-proximity-recommendation)',
                    ],
                    'timeout' => 2.5,
                    'max_duration' => 2.5,
                ]);

                $responses[] = $response;
                $responseAddressMap[spl_object_id($response)] = $address;
            }

            if ($responses !== []) {
                foreach ($this->httpClient->stream($responses, 3.5) as $response => $chunk) {
                    if (!$chunk->isLast()) {
                        continue;
                    }

                    $address = $responseAddressMap[spl_object_id($response)] ?? null;
                    if ($address === null) {
                        continue;
                    }

                    try {
                        $payload = $response->toArray(false);
                        $results[$address] = $this->extractCoordinatesFromPayload($payload);
                    } catch (\Throwable) {
                        $results[$address] = null;
                    }

                    $this->geocodeCache[$address] = $results[$address];
                }
            }
        } catch (\Throwable) {
            // Network failures should not break the appointment page.
        }

        foreach ($addresses as $address) {
            if (!array_key_exists($address, $results)) {
                $this->geocodeCache[$address] = null;
                $results[$address] = null;
            }
        }

        return $results;
    }

    /**
     * @param mixed $payload
     * @return array{lat: float, lng: float}|null
     */
    private function extractCoordinatesFromPayload(mixed $payload): ?array
    {
        if (!is_array($payload) || empty($payload[0]) || !is_array($payload[0])) {
            return null;
        }

        $first = $payload[0];

        if (!isset($first['lat'], $first['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildAddressQueries(Medecin $medecin): array
    {
        $cabinet = trim((string) $medecin->getAdresseCabinet());
        $ville = trim((string) $medecin->getVille());
        $queries = [];

        if ($cabinet !== '') {
            if ($ville !== '') {
                $queries[] = sprintf('%s, %s, Tunisia', $cabinet, $ville);
            }
            $queries[] = sprintf('%s, Tunisia', $cabinet);
        }
        if ($ville !== '') {
            $queries[] = sprintf('%s, Tunisia', $ville);
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * @param array{lat: float, lng: float} $coords
     * @return array<string, mixed>
     */
    private function buildRecommendationResult(
        Medecin $medecin,
        float $patientLat,
        float $patientLng,
        array $coords
    ): array {
        $distanceKm = $this->haversineDistanceKm(
            $patientLat,
            $patientLng,
            $coords['lat'],
            $coords['lng']
        );

        return [
            'id' => $medecin->getId(),
            'nom' => $medecin->getNom(),
            'prenom' => $medecin->getPrenom(),
            'specialite' => $medecin->getSpecialite(),
            'anneeExperience' => $medecin->getAnneeExperience(),
            'grade' => $medecin->getGrade(),
            'nomEtablissement' => $medecin->getNomEtablissement(),
            'adresseCabinet' => $medecin->getAdresseCabinet(),
            'distance_km' => round($distanceKm, 2),
        ];
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function resolveLocalCityCoordinates(?string $city): ?array
    {
        $normalized = $this->normalizeLocationToken((string) $city);
        if ($normalized === '') {
            return null;
        }

        return self::TUNISIA_CITY_COORDINATES[$normalized] ?? null;
    }

    private function normalizeLocationToken(string $value): string
    {
        $normalized = trim(mb_strtolower($value));
        if ($normalized === '') {
            return '';
        }

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($transliterated) && $transliterated !== '') {
            $normalized = $transliterated;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';
        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private function haversineDistanceKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadiusKm = 6371.0;

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadiusKm * $c;
    }
}
