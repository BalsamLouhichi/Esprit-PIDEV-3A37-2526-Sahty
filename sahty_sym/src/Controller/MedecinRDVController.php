<?php

namespace App\Controller;

use App\Entity\FicheMedicale;
use App\Entity\Medecin;
use App\Entity\RendezVous;
use App\Form\FicheMedicaleType;
use App\Repository\RendezVousRepository;
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
    private function getAuthenticatedMedecin(): Medecin
    {
        $user = $this->getUser();
        if (!$user instanceof Medecin) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    #[Route('/', name: 'app_medecin_rdv_liste', methods: ['GET'])]
    public function liste(
        Request $request,
        RendezVousRepository $rdvRepository
    ): Response {
        $medecin = $this->getAuthenticatedMedecin();
        $statutFiltre = (string) $request->query->get('statut', 'tous');
        $searchTerm = trim((string) $request->query->get('search', ''));

        $queryBuilder = $rdvRepository->createQueryBuilder('r')
            ->leftJoin('r.patient', 'p')
            ->where('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('r.dateRdv', 'DESC')
            ->addOrderBy('r.heureRdv', 'DESC');

        if ($statutFiltre !== 'tous') {
            $queryBuilder->andWhere('r.statut = :statut')
                ->setParameter('statut', $statutFiltre);
        }

        if ($searchTerm !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('LOWER(p.nom)', ':search'),
                    $queryBuilder->expr()->like('LOWER(p.prenom)', ':search'),
                    $queryBuilder->expr()->like('LOWER(r.raison)', ':search')
                )
            )->setParameter('search', '%' . strtolower($searchTerm) . '%');
        }

        return $this->render('medecin/rdv/liste.html.twig', [
            'rendez_vous' => $queryBuilder->getQuery()->getResult(),
            'statut_filtre' => $statutFiltre,
            'search_term' => $searchTerm,
        ]);
    }

    #[Route('/confirmer/{id}', name: 'app_medecin_rdv_confirmer', methods: ['POST'])]
    public function confirmer(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em,
        MeetingSchedulerService $meetingSchedulerService,
        AppointmentNotificationMailer $appointmentNotificationMailer
    ): Response {
        $rdv = $rdvRepository->find($id);
        if (!$rdv instanceof RendezVous) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        $rdvMedecin = $rdv->getMedecin();
        if (!$rdvMedecin instanceof Medecin || $rdvMedecin->getId() !== $this->getAuthenticatedMedecin()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($rdv->getStatut() !== 'en attente') {
            $this->addFlash('error', 'Ce rendez-vous ne peut pas etre confirme');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        $rdv->setStatut('Confirmé');
        $rdv->setDateValidation(new \DateTime());

        if ($rdv->getTypeConsultation() === 'en_ligne') {
            try {
                $meeting = $meetingSchedulerService->createForRendezVous($rdv);
                $rdv->setMeetingProvider((string) $meeting['provider']);
                $rdv->setMeetingUrl((string) $meeting['url']);
                $rdv->setMeetingCreatedAt(new \DateTimeImmutable());
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de generer le lien de consultation en ligne: ' . $e->getMessage());
                return $this->redirectToRoute('app_medecin_rdv_liste');
            }
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

    #[Route('/annuler/{id}', name: 'app_medecin_rdv_annuler', methods: ['POST'])]
    public function annuler(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em,
        AppointmentNotificationMailer $appointmentNotificationMailer
    ): Response {
        $rdv = $rdvRepository->find($id);
        if (!$rdv instanceof RendezVous) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        $rdvMedecin = $rdv->getMedecin();
        if (!$rdvMedecin instanceof Medecin || $rdvMedecin->getId() !== $this->getAuthenticatedMedecin()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', 'Ce rendez-vous est deja annule');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        $rdv->setStatut('Annulé');
        $em->flush();

        try {
            $appointmentNotificationMailer->sendCancellationToPatient($rdv);
        } catch (\Throwable) {
            $this->addFlash('warning', 'Rendez-vous annule, mais l email de notification au patient a echoue.');
        }

        $this->addFlash('success', 'Rendez-vous annule avec succes');
        return $this->redirectToRoute('app_medecin_rdv_liste');
    }

    #[Route('/details/{id}', name: 'app_medecin_rdv_details', methods: ['GET'])]
    public function details(
        int $id,
        RendezVousRepository $rdvRepository
    ): Response {
        $rdv = $rdvRepository->find($id);
        if (!$rdv instanceof RendezVous) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        $rdvMedecin = $rdv->getMedecin();
        if (!$rdvMedecin instanceof Medecin || $rdvMedecin->getId() !== $this->getAuthenticatedMedecin()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('medecin/rdv/details.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    #[Route('/fiche/{rdvId}', name: 'app_medecin_fiche_medicale', methods: ['GET', 'POST'])]
    public function ficheMedicale(
        int $rdvId,
        Request $request,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em
    ): Response {
        $rdv = $rdvRepository->find($rdvId);
        if (!$rdv instanceof RendezVous) {
            throw $this->createNotFoundException('Rendez-vous non trouve');
        }

        $rdvMedecin = $rdv->getMedecin();
        if (!$rdvMedecin instanceof Medecin || $rdvMedecin->getId() !== $this->getAuthenticatedMedecin()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', 'Impossible de creer/modifier une fiche medicale pour un rendez-vous annule');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        $fiche = $rdv->getFicheMedicale();
        $isNew = false;

        if (!$fiche instanceof FicheMedicale) {
            $fiche = new FicheMedicale();
            $fiche->setPatient($rdv->getPatient());
            $fiche->setCreeLe(new \DateTime());
            $fiche->setStatut('actif');
            $isNew = true;
        }

        $form = $this->createForm(FicheMedicaleType::class, $fiche, [
            'is_medecin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($fiche->getTaille() && $fiche->getPoids()) {
                $taille = (float) $fiche->getTaille();
                $poids = (float) $fiche->getPoids();
                $imc = $poids / ($taille * $taille);
                $fiche->setImc($imc);

                if ($imc < 18.5) {
                    $fiche->setCategorieImc('Maigreur');
                } elseif ($imc < 25.0) {
                    $fiche->setCategorieImc('Normal');
                } elseif ($imc < 30.0) {
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
