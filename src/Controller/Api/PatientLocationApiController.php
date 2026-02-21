<?php

namespace App\Controller\Api;

use App\Entity\Patient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/patient/location', name: 'api_patient_location_')]
class PatientLocationApiController extends AbstractController
{
    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isGranted('ROLE_PATIENT')) {
            return new JsonResponse(['success' => false, 'error' => 'Acces refuse'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof Patient) {
            return new JsonResponse(['success' => false, 'error' => 'Acces reserve aux patients'], JsonResponse::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['success' => false, 'error' => 'JSON invalide'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $lat = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $lng = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
        $accuracy = isset($payload['accuracy']) ? (float) $payload['accuracy'] : null;

        if ($lat === null || $lng === null) {
            return new JsonResponse(['success' => false, 'error' => 'Latitude/longitude manquantes'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return new JsonResponse(['success' => false, 'error' => 'Coordonnees invalides'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $session->set('patient_last_location', [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'captured_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'patient_id' => $user->getId(),
        ]);

        return new JsonResponse([
            'success' => true,
            'stored' => true,
        ]);
    }

    #[Route('/latest', name: 'latest', methods: ['GET'])]
    public function latest(SessionInterface $session): JsonResponse
    {
        if (!$this->isGranted('ROLE_PATIENT')) {
            return new JsonResponse(['success' => false, 'error' => 'Acces refuse'], JsonResponse::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        if (!$user instanceof Patient) {
            return new JsonResponse(['success' => false, 'error' => 'Acces reserve aux patients'], JsonResponse::HTTP_FORBIDDEN);
        }

        $data = $session->get('patient_last_location');
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'error' => 'Aucune position enregistree'], JsonResponse::HTTP_NOT_FOUND);
        }

        $ownerId = (int) ($data['patient_id'] ?? 0);
        if ($ownerId !== (int) $user->getId()) {
            return new JsonResponse(['success' => false, 'error' => 'Aucune position pour ce patient'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'location' => [
                'latitude' => (float) ($data['latitude'] ?? 0.0),
                'longitude' => (float) ($data['longitude'] ?? 0.0),
                'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
                'captured_at' => (string) ($data['captured_at'] ?? ''),
            ],
        ]);
    }
}
