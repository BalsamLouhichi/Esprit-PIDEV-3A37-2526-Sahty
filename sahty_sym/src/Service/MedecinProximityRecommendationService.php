<?php

namespace App\Service;

use App\Entity\Medecin;

class MedecinProximityRecommendationService
{
    /**
     * @param Medecin[] $medecins
     * @return array<int, array<string, mixed>>
     */
    public function recommendNearest(
        array $medecins,
        float $patientLatitude,
        float $patientLongitude,
        int $limit,
        float $maxKm
    ): array {
        $items = [];

        foreach ($medecins as $medecin) {
            if (!$medecin instanceof Medecin) {
                continue;
            }

            // Fallback static ranking: no geocoordinates available on Medecin entity.
            $items[] = [
                'id' => $medecin->getId(),
                'nom' => $medecin->getNom(),
                'prenom' => $medecin->getPrenom(),
                'specialite' => $medecin->getSpecialite(),
                'adresse_cabinet' => $medecin->getAdresseCabinet(),
                'distance_km' => null,
                'within_max_km' => true,
                'patient_latitude' => $patientLatitude,
                'patient_longitude' => $patientLongitude,
                'max_km' => $maxKm,
            ];
        }

        return array_slice($items, 0, max(1, $limit));
    }
}

