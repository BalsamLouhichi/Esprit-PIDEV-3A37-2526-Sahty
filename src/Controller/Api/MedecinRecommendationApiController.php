<?php

namespace App\Controller\Api;

use App\Entity\Patient;
use App\Repository\MedecinRepository;
use App\Service\MedecinProximityRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/rdv/recommendation', name: 'api_rdv_recommendation_')]
class MedecinRecommendationApiController extends AbstractController
{
    #[Route('/nearest', name: 'nearest', methods: ['GET'])]
    public function nearest(
        Request $request,
        SessionInterface $session,
        MedecinRepository $medecinRepository,
        MedecinProximityRecommendationService $recommendationService
    ): JsonResponse {
        if (!$this->isGranted('ROLE_PATIENT')) {
            return new JsonResponse(['success' => false, 'error' => 'Acces refuse'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof Patient) {
            return new JsonResponse(['success' => false, 'error' => 'Acces reserve aux patients'], JsonResponse::HTTP_FORBIDDEN);
        }

        $location = $session->get('patient_last_location');
        if (!is_array($location)) {
            return new JsonResponse(
                ['success' => false, 'error' => 'Position patient non disponible'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $ownerId = (int) ($location['patient_id'] ?? 0);
        if ($ownerId !== (int) $user->getId()) {
            return new JsonResponse(
                ['success' => false, 'error' => 'Aucune position pour ce patient'],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $patientLat = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $patientLng = isset($location['longitude']) ? (float) $location['longitude'] : null;
        if ($patientLat === null || $patientLng === null) {
            return new JsonResponse(
                ['success' => false, 'error' => 'Coordonnees patient invalides'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $limit = max(1, min(10, (int) $request->query->get('limit', 5)));
        $specialite = mb_strtolower(trim((string) $request->query->get('specialite', '')));

        $medecins = $medecinRepository->findBy(['estActif' => true]);
        if ($specialite !== '') {
            $medecins = array_values(array_filter(
                $medecins,
                static fn ($medecin): bool => str_contains(
                    mb_strtolower((string) $medecin->getSpecialite()),
                    $specialite
                )
            ));
        }

        $recommendations = $recommendationService->recommendNearest(
            $medecins,
            $patientLat,
            $patientLng,
            $limit
        );

        return new JsonResponse([
            'success' => true,
            'patient_location' => [
                'latitude' => $patientLat,
                'longitude' => $patientLng,
                'captured_at' => (string) ($location['captured_at'] ?? ''),
            ],
            'count' => count($recommendations),
            'recommendations' => $recommendations,
        ]);
    }
}
