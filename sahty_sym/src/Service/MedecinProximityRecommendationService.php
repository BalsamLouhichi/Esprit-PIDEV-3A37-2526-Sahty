<?php

namespace App\Service;

use App\Entity\Medecin;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MedecinProximityRecommendationService
{
    private const GEOCODER_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

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
        $medecins = array_slice($medecins, 0, 15);
        $results = [];
        $candidates = [];

        foreach ($medecins as $medecin) {
            if (!$medecin->isEstActif()) {
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
        }

        $addressQueries = [];
        foreach ($candidates as $item) {
            foreach ($item['queries'] as $query) {
                if ($query !== '') {
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

            $distanceKm = $this->haversineDistanceKm(
                $patientLat,
                $patientLng,
                $coords['lat'],
                $coords['lng']
            );

            if ($maxDistanceKm !== null && $distanceKm > $maxDistanceKm) {
                continue;
            }

            $results[] = [
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
                'timeout' => 2.0,
            ]);

            $responses[] = $response;
            $responseAddressMap[spl_object_id($response)] = $address;
        }

        if ($responses !== []) {
            foreach ($this->httpClient->stream($responses, 3.0) as $response => $chunk) {
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

        return array_values(array_unique($queries));
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
