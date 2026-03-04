<?php

namespace App\Controller;

use App\Entity\Medecin;
use App\Entity\RendezVous;
use App\Entity\FicheMedicale;
use App\Form\FicheMedicaleType;
use App\Repository\RendezVousRepository;
use App\Repository\FicheMedicaleRepository;
use App\Service\AppointmentNotificationMailer;
use App\Service\DictationTranscriptionService;
use App\Service\MeetingSchedulerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/medecin/rdv')]
#[IsGranted('ROLE_MEDECIN')]
class MedecinRDVController extends AbstractController
{
    /**
     * ÃƒÆ’Ã‚Â°Ãƒâ€¦Ã‚Â¸ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Liste des rendez-vous du mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©decin avec recherche et filtrage
     */
    #[Route('/', name: 'app_medecin_rdv_liste', methods: ['GET'])]
    public function liste(
        Request $request,
        RendezVousRepository $rdvRepository
    ): Response {
        $medecin = $this->getUser();

        if (!$medecin instanceof Medecin) {
            throw $this->createAccessDeniedException();
        }

        // RÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rer les paramÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¨tres de recherche et filtrage
        $statutFiltre = $request->query->get('statut', 'tous');
        $searchTerm = $request->query->get('search', '');

        // Construction de la requÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªte de base
        $queryBuilder = $rdvRepository->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->where('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('r.dateRdv', 'DESC')
            ->addOrderBy('r.heureRdv', 'DESC');

        // Filtrage par statut
        if ($statutFiltre !== 'tous') {
            $queryBuilder->andWhere('r.statut = :statut')
                ->setParameter('statut', $statutFiltre);
        }

        // ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Recherche textuelle (nom, prÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©nom, raison)
        if (!empty($searchTerm)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('LOWER(p.nom)', ':search'),
                    $queryBuilder->expr()->like('LOWER(p.prenom)', ':search'),
                    $queryBuilder->expr()->like('LOWER(r.raison)', ':search')
                )
            )->setParameter('search', '%' . strtolower($searchTerm) . '%');
        }

        $rdvs = $queryBuilder->getQuery()->getResult();

        return $this->render('medecin/rdv/liste.html.twig', [
            'rendez_vous' => $rdvs,
            'statut_filtre' => $statutFiltre,
            'search_term' => $searchTerm,
        ]);
    }

    /**
     * ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ Confirmer un rendez-vous
     */
    #[Route('/confirmer/{id}', name: 'app_medecin_rdv_confirmer', methods: ['POST'])]
    public function confirmer(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em,
        MeetingSchedulerService $meetingSchedulerService,
        AppointmentNotificationMailer $appointmentNotificationMailer
    ): Response {
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        // VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que c'est le mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que le RDV est en attente
        if ($rdv->getStatut() !== 'en attente') {
            $this->addFlash('error', 'Ce rendez-vous ne peut pas etre confirme');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // Confirmer
        $rdv->setStatut("Confirm\u{00E9}");
        $rdv->setDateValidation(new \DateTime());
        if ($rdv->getTypeConsultation() === 'en_ligne') {
            try {
                $meeting = $meetingSchedulerService->createForRendezVous($rdv);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de generer le lien de consultation en ligne: ' . $e->getMessage());
                return $this->redirectToRoute('app_medecin_rdv_liste');
            }

            $rdv->setMeetingProvider($meeting['provider']);
            $rdv->setMeetingUrl($meeting['url']);
            $rdv->setMeetingCreatedAt(new \DateTimeImmutable());
        }
        $em->flush();
        try {
            $appointmentNotificationMailer->sendConfirmationToPatient($rdv);
        } catch (\Throwable) {
            $this->addFlash('warning', 'Rendez-vous confirme, mais l email de notification au patient a echoue.');
        }

        $this->addFlash('success', 'Rendez-vous confirme avec succes');
        return $this->redirectToRoute('app_medecin_rdv_liste');
    }

    /**
     * ÃƒÆ’Ã‚Â¢Ãƒâ€šÃ‚ÂÃƒâ€¦Ã¢â‚¬â„¢ Annuler un rendez-vous
     */
    #[Route('/annuler/{id}', name: 'app_medecin_rdv_annuler', methods: ['POST'])]
    public function annuler(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em,
        AppointmentNotificationMailer $appointmentNotificationMailer
    ): Response {
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        // VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que c'est le mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que le RDV n'est pas dÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©jÃƒÆ’Ã†â€™Ãƒâ€šÃ‚  annulÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©
        if ($rdv->getStatut() === "Annul\u{00E9}") {
            $this->addFlash('error', 'Ce rendez-vous est deja annule');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // Annuler
        $rdv->setStatut("Annul\u{00E9}");
        $em->flush();
        try {
            $appointmentNotificationMailer->sendCancellationToPatient($rdv);
        } catch (\Throwable) {
            $this->addFlash('warning', 'Rendez-vous annule, mais l email de notification au patient a echoue.');
        }

        $this->addFlash('success', 'Rendez-vous annule avec succes');
        return $this->redirectToRoute('app_medecin_rdv_liste');
    }

    /**
     * ÃƒÆ’Ã‚Â°Ãƒâ€¦Ã‚Â¸ÃƒÂ¢Ã¢â€šÂ¬Ã‹Å“Ãƒâ€šÃ‚ÂÃƒÆ’Ã‚Â¯Ãƒâ€šÃ‚Â¸Ãƒâ€šÃ‚Â Voir les dÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©tails d'un rendez-vous
     */
    #[Route('/details/{id}', name: 'app_medecin_rdv_details', methods: ['GET'])]
    public function details(
        int $id,
        RendezVousRepository $rdvRepository
    ): Response {
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        // VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que c'est le mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('medecin/rdv/details.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    /**
     * ÃƒÆ’Ã‚Â°Ãƒâ€¦Ã‚Â¸ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œÃƒâ€šÃ‚Â ComplÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©ter/Modifier la fiche mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©dicale d'un patient
     */
    #[Route('/fiche/{rdvId}', name: 'app_medecin_fiche_medicale', methods: ['GET', 'POST'])]
    public function ficheMedicale(
        int $rdvId,
        Request $request,
        RendezVousRepository $rdvRepository,
        FicheMedicaleRepository $ficheRepository,
        EntityManagerInterface $em,
        DictationTranscriptionService $dictationTranscriptionService
    ): Response {
        $rdv = $rdvRepository->find($rdvId);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        $dictationStatus = $dictationTranscriptionService->getConfigurationStatus();
        if (!($dictationStatus['ok'] ?? false)) {
            $this->addFlash('error', 'DictÃ©e vocale indisponible: ' . (string) ($dictationStatus['error'] ?? 'Configuration invalide.'));
        }

        // VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que c'est le mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // ÃƒÆ’Ã‚Â¢Ãƒâ€¦Ã¢â‚¬Å“ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ VÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rifier que le RDV n'est pas annulÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©
        if ($rdv->getStatut() === "Annul\u{00E9}") {
            $this->addFlash('error', 'Impossible de creer/modifier une fiche medicale pour un rendez-vous annule');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // RÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©cupÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rer ou crÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©er la fiche mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©dicale
        $fiche = $rdv->getFicheMedicale();
        $isNew = false;

        if (!$fiche) {
            // CrÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©er une nouvelle fiche
            $fiche = new FicheMedicale();
            $fiche->setPatient($rdv->getPatient());
            $fiche->setCreeLe(new \DateTime());
            $fiche->setStatut('actif');
            $isNew = true;
        }

        // Formulaire avec permissions mÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©decin
        $form = $this->createForm(FicheMedicaleType::class, $fiche, [
            'is_medecin' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculer l'IMC si nÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©cessaire
            if ($fiche->getTaille() && $fiche->getPoids()) {
                $imc = $fiche->getPoids() / ($fiche->getTaille() * $fiche->getTaille());
                $fiche->setImc($imc);

                if ($imc < 18.5) {
                    $fiche->setCategorieImc('Maigreur');
                } elseif ($imc < 25) {
                    $fiche->setCategorieImc('Normal');
                } elseif ($imc < 30) {
                    $fiche->setCategorieImc('Surpoids');
                } else {
                    $fiche->setCategorieImc('Obesite');
                }
            }

            if ($isNew) {
                $rdv->setFicheMedicale($fiche);
                $em->persist($fiche);
            } else {
                $fiche->setModifieLe(new \DateTime());
            }

            $em->flush();

            $this->addFlash('success', 'Fiche medicale enregistree avec succes');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        return $this->render('medecin/rdv/fiche_medicale.html.twig', [
            'form' => $form->createView(),
            'rdv' => $rdv,
            'fiche' => $fiche,
            'isNew' => $isNew,
        ]);
    }
}