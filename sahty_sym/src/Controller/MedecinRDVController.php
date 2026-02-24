<?php

namespace App\Controller;

use App\Entity\Medecin;
use App\Entity\RendezVous;
use App\Entity\FicheMedicale;
use App\Form\FicheMedicaleType;
use App\Repository\RendezVousRepository;
use App\Repository\FicheMedicaleRepository;
use App\Service\AppointmentNotificationMailer;
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
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬Â¦ Liste des rendez-vous du mÃƒÆ’Ã‚Â©decin avec recherche et filtrage
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

        // RÃƒÆ’Ã‚Â©cupÃƒÆ’Ã‚Â©rer les paramÃƒÆ’Ã‚Â¨tres de recherche et filtrage
        $statutFiltre = $request->query->get('statut', 'tous');
        $searchTerm = $request->query->get('search', '');

        // Construction de la requÃƒÆ’Ã‚Âªte de base
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

        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Recherche textuelle (nom, prÃƒÆ’Ã‚Â©nom, raison)
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
     * ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ Confirmer un rendez-vous
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

        // VÃƒÆ’Ã‚Â©rifier que c'est le mÃƒÆ’Ã‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // VÃƒÆ’Ã‚Â©rifier que le RDV est en attente
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
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Annuler un rendez-vous
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

        // VÃƒÆ’Ã‚Â©rifier que c'est le mÃƒÆ’Ã‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // VÃƒÆ’Ã‚Â©rifier que le RDV n'est pas dÃƒÆ’Ã‚Â©jÃƒÆ’Ã‚Â  annulÃƒÆ’Ã‚Â©
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
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ËœÃ‚ÂÃƒÂ¯Ã‚Â¸Ã‚Â Voir les dÃƒÆ’Ã‚Â©tails d'un rendez-vous
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

        // VÃƒÆ’Ã‚Â©rifier que c'est le mÃƒÆ’Ã‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('medecin/rdv/details.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    /**
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â ComplÃƒÆ’Ã‚Â©ter/Modifier la fiche mÃƒÆ’Ã‚Â©dicale d'un patient
     */
    #[Route('/fiche/{rdvId}', name: 'app_medecin_fiche_medicale', methods: ['GET', 'POST'])]
    public function ficheMedicale(
        int $rdvId,
        Request $request,
        RendezVousRepository $rdvRepository,
        FicheMedicaleRepository $ficheRepository,
        EntityManagerInterface $em
    ): Response {
        $rdv = $rdvRepository->find($rdvId);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        // VÃƒÆ’Ã‚Â©rifier que c'est le mÃƒÆ’Ã‚Â©decin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ VÃƒÆ’Ã‚Â©rifier que le RDV n'est pas annulÃƒÆ’Ã‚Â©
        if ($rdv->getStatut() === "Annul\u{00E9}") {
            $this->addFlash('error', 'Impossible de creer/modifier une fiche medicale pour un rendez-vous annule');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // RÃƒÆ’Ã‚Â©cupÃƒÆ’Ã‚Â©rer ou crÃƒÆ’Ã‚Â©er la fiche mÃƒÆ’Ã‚Â©dicale
        $fiche = $rdv->getFicheMedicale();
        $isNew = false;

        if (!$fiche) {
            // CrÃƒÆ’Ã‚Â©er une nouvelle fiche
            $fiche = new FicheMedicale();
            $fiche->setPatient($rdv->getPatient());
            $fiche->setCreeLe(new \DateTime());
            $fiche->setStatut('actif');
            $isNew = true;
        }

        // Formulaire avec permissions mÃƒÆ’Ã‚Â©decin
        $form = $this->createForm(FicheMedicaleType::class, $fiche, [
            'is_medecin' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculer l'IMC si nÃƒÆ’Ã‚Â©cessaire
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