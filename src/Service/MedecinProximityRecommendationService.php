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

        foreach ($medecins as $medecin) {
            if (!$medecin instanceof Medecin || !$medecin->isEstActif()) {
                continue;
            }

            $adresseCabinet = trim((string) $medecin->getAdresseCabinet());
            if ($adresseCabinet === '') {
                continue;
            }

            $coords = $this->geocodeAddress($this->buildTunisiaAddressQuery($medecin));
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
     * @return array{lat: float, lng: float}|null
     */
    private function geocodeAddress(string $address): ?array
    {
        if (array_key_exists($address, $this->geocodeCache)) {
            return $this->geocodeCache[$address];
        }

        try {
            $response = $this->httpClient->request('GET', self::GEOCODER_ENDPOINT, [
                'query' => [
                    'q' => $address,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'tn',
                    'addressdetails' => 1,
                ],
                'headers' => [
                    'User-Agent' => 'SahtySym/1.0 (doctor-proximity-recommendation)',
                ],
                'timeout' => 2.5,
            ]);

            $payload = $response->toArray(false);
            if (!is_array($payload) || empty($payload[0])) {
                $this->geocodeCache[$address] = null;
                return null;
            }

            $first = $payload[0];
            $countryCode = mb_strtolower((string) ($first['address']['country_code'] ?? $first['country_code'] ?? ''));
            if ($countryCode !== '' && $countryCode !== 'tn') {
                $this->geocodeCache[$address] = null;
                return null;
            }

            $lat = isset($first['lat']) ? (float) $first['lat'] : null;
            $lng = isset($first['lon']) ? (float) $first['lon'] : null;

            if ($lat === null || $lng === null) {
                $this->geocodeCache[$address] = null;
                return null;
            }

            $this->geocodeCache[$address] = ['lat' => $lat, 'lng' => $lng];
            return $this->geocodeCache[$address];
        } catch (\Throwable) {
            $this->geocodeCache[$address] = null;
            return null;
        }
    }

    private function buildTunisiaAddressQuery(Medecin $medecin): string
    {
        $parts = [];
        $cabinet = trim((string) $medecin->getAdresseCabinet());
        $ville = trim((string) $medecin->getVille());

        if ($cabinet !== '') {
            $parts[] = $cabinet;
        }
        if ($ville !== '') {
            $parts[] = $ville;
        }
        $parts[] = 'Tunisia';

        return implode(', ', $parts);
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
