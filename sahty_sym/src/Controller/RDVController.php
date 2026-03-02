<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\FicheMedicale;
use App\Entity\Patient;
use App\Entity\Medecin;
use App\Form\RendezVousType;
use App\Repository\MedecinRepository;
use App\Repository\RendezVousRepository;
use App\Service\ConsultationDurationAiService;
use App\Service\MedecinProximityRecommendationService;
use App\Service\PatientAppointmentGuidanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RDVController extends AbstractController
{
    private const SESSION_KEY_PATIENT_LOCATION = 'patient_location_latest';

    /**
     * 📋 Page de prise de rendez-vous (GET/POST)
     */
    #[Route('/rdv/prendre', name: 'app_rdv_prendre', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function prendre(
        Request $request,
        EntityManagerInterface $em,
        MedecinRepository $medecinRepository
    ): Response {
        $patient = $this->getUser();
        if (!$patient instanceof Patient) {
            $this->addFlash('error', '❌ Seuls les patients peuvent prendre rendez-vous');
            return $this->redirectToRoute('home');
        }

        $rdv = new RendezVous();
        $rdv->setPatient($patient);

        $form = $this->createForm(RendezVousType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$rdv->getMedecin()) {
                $this->addFlash('error', '❌ Veuillez sélectionner un médecin');
                return $this->redirectToRoute('app_rdv_prendre');
            }

            if (!$rdv->getDateRdv() || !$rdv->getHeureRdv()) {
                $this->addFlash('error', '❌ Veuillez sélectionner une date et une heure');
                return $this->redirectToRoute('app_rdv_prendre');
            }

            $rdvDate = $rdv->getDateRdv();
            $rdvHeure = $rdv->getHeureRdv();
            if (!$rdvDate instanceof \DateTimeInterface || !$rdvHeure instanceof \DateTimeInterface) {
                $this->addFlash('error', '❌ Date/heure du rendez-vous invalide');
                return $this->redirectToRoute('app_rdv_prendre');
            }

            $rdvDateTime = new \DateTime();
            $rdvDateTime->setDate(
                (int) $rdvDate->format('Y'),
                (int) $rdvDate->format('m'),
                (int) $rdvDate->format('d')
            );
            $rdvDateTime->setTime(
                (int) $rdvHeure->format('H'),
                (int) $rdvHeure->format('i')
            );

            if ($rdvDateTime < new \DateTime()) {
                $this->addFlash('error', '❌ La date et l\'heure doivent être dans le futur');
                return $this->redirectToRoute('app_rdv_prendre');
            }

            $conflictingRdv = $em->getRepository(RendezVous::class)->findBy([
                'medecin' => $rdv->getMedecin(),
                'dateRdv' => $rdv->getDateRdv(),
                'heureRdv' => $rdv->getHeureRdv(),
                'statut' => 'en attente'
            ]);

            if (!empty($conflictingRdv)) {
                $this->addFlash('error', '⚠️ Ce créneau horaire est déjà réservé. Veuillez choisir un autre créneau');
                return $this->redirectToRoute('app_rdv_prendre');
            }

            $rdv->setStatut('en attente');
            $rdv->setCreeLe(new \DateTime());

            $em->persist($rdv);
            $em->flush();

            $this->addFlash('success', '✅ Rendez-vous confirmé avec succès!');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $medecinsActifs = $medecinRepository->findBy(['estActif' => true]);

        return $this->render('rdv/prendre.html.twig', [
            'form' => $form->createView(),
            'medecins' => $medecinsActifs,
            'medecins_json' => array_map(
                fn (Medecin $medecin) => $this->buildMedecinPayload($medecin),
                $medecinsActifs
            ),
        ]);
    }

    /**
     * ✏️ Modifier un rendez-vous existant
     */
    #[Route('/rdv/modifier/{id}', name: 'app_rdv_modifier', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function modifier(
        int $id,
        Request $request,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em,
        MedecinRepository $medecinRepository
    ): Response {
        $currentPatient = $this->getUser();
        if (!$currentPatient instanceof Patient) {
            throw $this->createAccessDeniedException('Accès patient requis');
        }

        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        if ($rdv->getPatient()?->getId() !== $currentPatient->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce rendez-vous');
        }

        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', '❌ Impossible de modifier un rendez-vous annulé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdvDate = $rdv->getDateRdv();
        $rdvHeure = $rdv->getHeureRdv();
        if (!$rdvDate instanceof \DateTimeInterface || !$rdvHeure instanceof \DateTimeInterface) {
            $this->addFlash('error', '❌ Date/heure du rendez-vous invalide');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdvDateTime = new \DateTime();
        $rdvDateTime->setDate(
            (int) $rdvDate->format('Y'),
            (int) $rdvDate->format('m'),
            (int) $rdvDate->format('d')
        );
        $rdvDateTime->setTime(
            (int) $rdvHeure->format('H'),
            (int) $rdvHeure->format('i')
        );

        if ($rdvDateTime < new \DateTime()) {
            $this->addFlash('error', '❌ Impossible de modifier un rendez-vous passé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $oldMedecin = $rdv->getMedecin();
        $oldDate = $rdv->getDateRdv();
        $oldHeure = $rdv->getHeureRdv();
        if (!$oldMedecin || !$oldDate instanceof \DateTimeInterface || !$oldHeure instanceof \DateTimeInterface) {
            $this->addFlash('error', '❌ Données historiques du rendez-vous invalides');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $form = $this->createForm(RendezVousType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$rdv->getMedecin()) {
                $this->addFlash('error', '❌ Veuillez sélectionner un médecin');
                return $this->redirectToRoute('app_rdv_modifier', ['id' => $id]);
            }

            if (!$rdv->getDateRdv() || !$rdv->getHeureRdv()) {
                $this->addFlash('error', '❌ Veuillez sélectionner une date et une heure');
                return $this->redirectToRoute('app_rdv_modifier', ['id' => $id]);
            }

            $newDate = $rdv->getDateRdv();
            $newHeure = $rdv->getHeureRdv();
            if (!$newDate instanceof \DateTimeInterface || !$newHeure instanceof \DateTimeInterface) {
                $this->addFlash('error', '❌ Date/heure du rendez-vous invalide');
                return $this->redirectToRoute('app_rdv_modifier', ['id' => $id]);
            }

            $newRdvDateTime = new \DateTime();
            $newRdvDateTime->setDate(
                (int) $newDate->format('Y'),
                (int) $newDate->format('m'),
                (int) $newDate->format('d')
            );
            $newRdvDateTime->setTime(
                (int) $newHeure->format('H'),
                (int) $newHeure->format('i')
            );

            if ($newRdvDateTime < new \DateTime()) {
                $this->addFlash('error', '❌ La date et l\'heure doivent être dans le futur');
                return $this->redirectToRoute('app_rdv_modifier', ['id' => $id]);
            }

            $creneauChanged = (
                $rdv->getMedecin()->getId() !== $oldMedecin->getId() ||
                $rdv->getDateRdv()->format('Y-m-d') !== $oldDate->format('Y-m-d') ||
                $rdv->getHeureRdv()->format('H:i') !== $oldHeure->format('H:i')
            );

            if ($creneauChanged) {
                $conflictingRdv = $em->getRepository(RendezVous::class)->createQueryBuilder('r')
                    ->where('r.medecin = :medecin')
                    ->andWhere('r.dateRdv = :date')
                    ->andWhere('r.heureRdv = :heure')
                    ->andWhere('r.statut = :statut')
                    ->andWhere('r.id != :currentId')
                    ->setParameter('medecin', $rdv->getMedecin())
                    ->setParameter('date', $rdv->getDateRdv())
                    ->setParameter('heure', $rdv->getHeureRdv())
                    ->setParameter('statut', 'en attente')
                    ->setParameter('currentId', $id)
                    ->getQuery()
                    ->getResult();

                if (!empty($conflictingRdv)) {
                    $this->addFlash('error', '⚠️ Ce créneau horaire est déjà réservé. Veuillez choisir un autre créneau');
                    return $this->redirectToRoute('app_rdv_modifier', ['id' => $id]);
                }
            }

            $em->flush();

            $this->addFlash('success', '✅ Rendez-vous modifié avec succès!');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        return $this->render('rdv/modifier.html.twig', [
            'form' => $form->createView(),
            'rdv' => $rdv,
            'medecins' => $medecinRepository->findBy(['estActif' => true]),
        ]);
    }

    private function buildMedecinPayload(Medecin $medecin): array
    {
        return [
            'id' => $medecin->getId(),
            'nom' => $medecin->getNom(),
            'prenom' => $medecin->getPrenom(),
            'specialite' => $medecin->getSpecialite(),
            'anneeExperience' => $medecin->getAnneeExperience(),
            'grade' => $medecin->getGrade(),
            'nomEtablissement' => $medecin->getNomEtablissement(),
            'adresseCabinet' => $medecin->getAdresseCabinet(),
        ];
    }

    /**
     * 📅 Liste des rendez-vous du patient
     */
    #[Route('/rdv/mes-rdv', name: 'app_rdv_mes_rdv')]
    #[IsGranted('ROLE_PATIENT')]
    public function mesRendezVous(
        RendezVousRepository $rdvRepository,
        Request $request,
        ConsultationDurationAiService $consultationDurationAiService
    ): Response {
        $patient = $this->getUser();

        if (!$patient instanceof Patient) {
            throw $this->createAccessDeniedException();
        }

        $perPage = 6;
        $totalItems = $rdvRepository->countByPatientEntity($patient);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = max(1, (int) $request->query->get('page', 1));
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $rdvs = $rdvRepository->findPageByPatient($patient, $currentPage, $perPage);
        $allStatuses = $rdvRepository->findStatusesByPatient($patient);

        $stats = [
            'total' => $totalItems,
            'attente' => count(array_filter($allStatuses, static fn (string $status): bool => strtolower(trim($status)) === 'en attente')),
            'confirme' => count(array_filter(
                $allStatuses,
                static fn (string $status): bool => str_starts_with(strtolower(str_replace('é', 'e', $status)), 'confirm')
            )),
            'annule' => count(array_filter(
                $allStatuses,
                static fn (string $status): bool => str_starts_with(strtolower(str_replace('é', 'e', $status)), 'annul')
            )),
        ];

        $session = $request->getSession();
        $predictions = (array) $session->get('consultation_duration_predictions', []);
        $updated = false;

        foreach ($rdvs as $rdv) {
            $rdvId = (string) $rdv->getId();
            if (
                isset($predictions[$rdvId]) &&
                array_key_exists('minutes', $predictions[$rdvId]) &&
                $predictions[$rdvId]['minutes'] !== null
            ) {
                continue;
            }

            $fiche = $rdv->getFicheMedicale();
            $minutes = $consultationDurationAiService->predict($rdv, $fiche);
            if ($minutes === null) {
                $minutes = 22.0;
            }

            $predictions[$rdvId] = [
                'minutes' => number_format($minutes, 1, '.', ''),
                'updated_at' => (new \DateTimeImmutable())->format('c'),
            ];
            $updated = true;
        }

        if ($updated) {
            $session->set('consultation_duration_predictions', $predictions);
        }

        return $this->render('rdv/mes_rdv.html.twig', [
            'rendez_vous' => $rdvs,
            'consultation_duration_predictions' => $predictions,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'stats' => $stats,
        ]);
    }

    #[Route('/rdv/hide-duration-prediction/{id}', name: 'app_rdv_hide_duration_prediction', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function hideDurationPrediction(int $id, Request $request, RendezVousRepository $rdvRepository): JsonResponse
    {
        $rdv = $rdvRepository->find($id);
        if (!$rdv || !$rdv->getPatient() || $rdv->getPatient()->getId() !== $this->getUser()?->getId()) {
            return $this->json(['ok' => false, 'message' => 'Rendez-vous introuvable'], 404);
        }

        $session = $request->getSession();
        $predictions = (array) $session->get('consultation_duration_predictions', []);
        unset($predictions[(string) $id]);
        $session->set('consultation_duration_predictions', $predictions);

        return $this->json(['ok' => true]);
    }

    /**
     * 👁️ Consulter un rendez-vous (lecture seule pour RDV annulés)
     */
    #[Route('/rdv/consulter/{id}', name: 'app_rdv_consulter', methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function consulter(
        int $id,
        RendezVousRepository $rdvRepository
    ): Response {
        $currentPatient = $this->getUser();
        if (!$currentPatient instanceof Patient) {
            throw $this->createAccessDeniedException();
        }

        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        if ($rdv->getPatient()?->getId() !== $currentPatient->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('rdv/consulter.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    /**
     * ❌ Annuler un rendez-vous
     */
    #[Route('/rdv/annuler/{id}', name: 'app_rdv_annuler', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function annulerRendezVous(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em
    ): Response {
        $currentPatient = $this->getUser();
        if (!$currentPatient instanceof Patient) {
            throw $this->createAccessDeniedException();
        }

        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        if ($rdv->getPatient()?->getId() !== $currentPatient->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', '❌ Ce rendez-vous est déjà annulé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdvDate = $rdv->getDateRdv();
        $rdvHeure = $rdv->getHeureRdv();
        if (!$rdvDate instanceof \DateTimeInterface || !$rdvHeure instanceof \DateTimeInterface) {
            $this->addFlash('error', '❌ Date/heure du rendez-vous invalide');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdvDateTime = new \DateTime();
        $rdvDateTime->setDate(
            (int) $rdvDate->format('Y'),
            (int) $rdvDate->format('m'),
            (int) $rdvDate->format('d')
        );
        $rdvDateTime->setTime(
            (int) $rdvHeure->format('H'),
            (int) $rdvHeure->format('i')
        );

        if ($rdvDateTime < new \DateTime()) {
            $this->addFlash('error', '❌ Impossible d\'annuler un rendez-vous passé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdv->setStatut('Annulé');
        $em->flush();

        $this->addFlash('success', '✅ Rendez-vous annulé avec succès');
        return $this->redirectToRoute('app_rdv_mes_rdv');
    }

    #[Route('/api/patient/location/latest', name: 'api_patient_location_latest', methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function patientLocationLatest(Request $request): JsonResponse
    {
        $location = $request->getSession()->get(self::SESSION_KEY_PATIENT_LOCATION);
        if (!is_array($location)) {
            return $this->json([
                'success' => true,
                'location' => null,
            ]);
        }

        return $this->json([
            'success' => true,
            'location' => [
                'latitude' => (float) ($location['latitude'] ?? 0.0),
                'longitude' => (float) ($location['longitude'] ?? 0.0),
                'accuracy' => isset($location['accuracy']) ? (float) $location['accuracy'] : null,
                'updated_at' => (string) ($location['updated_at'] ?? ''),
            ],
        ]);
    }

    #[Route('/api/patient/location/save', name: 'api_patient_location_save', methods: ['POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function patientLocationSave(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'success' => false,
                'error' => 'Payload JSON invalide.',
            ], 400);
        }

        $latitude = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
        $longitude = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
        $accuracy = isset($payload['accuracy']) ? (float) $payload['accuracy'] : null;

        if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $this->json([
                'success' => false,
                'error' => 'Coordonnees invalides.',
            ], 422);
        }

        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        $request->getSession()->set(self::SESSION_KEY_PATIENT_LOCATION, $location);

        return $this->json([
            'success' => true,
            'location' => $location,
        ]);
    }

    #[Route('/api/rdv/recommendation/nearest', name: 'api_rdv_recommendation_nearest', methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function recommendationNearest(
        Request $request,
        MedecinRepository $medecinRepository,
        MedecinProximityRecommendationService $recommendationService
    ): JsonResponse {
        $location = $request->getSession()->get(self::SESSION_KEY_PATIENT_LOCATION);
        if (!is_array($location) || !isset($location['latitude'], $location['longitude'])) {
            return $this->json([
                'success' => false,
                'error' => 'Position patient manquante. Autorisez la geolocalisation.',
            ], 400);
        }

        $limit = max(1, min(10, (int) $request->query->get('limit', 3)));
        $maxKm = (float) $request->query->get('max_km', 25);
        if ($maxKm <= 0) {
            $maxKm = 25.0;
        }

        $medecinsActifs = $medecinRepository->findBy(['estActif' => true]);

        $recommendations = $recommendationService->recommendNearest(
            $medecinsActifs,
            (float) $location['latitude'],
            (float) $location['longitude'],
            $limit,
            $maxKm
        );

        $usedDistanceFallback = false;
        $fallbackMaxKm = null;
        if ($recommendations === []) {
            $fallbackMaxKm = max(60.0, $maxKm * 2);
            $recommendations = $recommendationService->recommendNearest(
                $medecinsActifs,
                (float) $location['latitude'],
                (float) $location['longitude'],
                $limit,
                $fallbackMaxKm
            );
            $usedDistanceFallback = true;
        }

        return $this->json([
            'success' => true,
            'recommendations' => $recommendations,
            'used_distance_fallback' => $usedDistanceFallback,
            'fallback_max_km' => $fallbackMaxKm,
        ]);
    }

    #[Route('/api/rdv/{id}/patient-guidance', name: 'api_rdv_patient_guidance', methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function patientGuidance(
        int $id,
        RendezVousRepository $rdvRepository,
        PatientAppointmentGuidanceService $guidanceService
    ): JsonResponse {
        $rdv = $rdvRepository->find($id);
        if (!$rdv || !$rdv->getPatient() || $rdv->getPatient()->getId() !== $this->getUser()?->getId()) {
            return $this->json([
                'success' => false,
                'error' => 'Rendez-vous introuvable.',
            ], 404);
        }

        $guidance = $guidanceService->generate($rdv);

        return $this->json([
            'success' => true,
            'guidance' => $guidance,
        ]);
    }
}

