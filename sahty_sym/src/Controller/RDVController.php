<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\FicheMedicale;
use App\Entity\Patient;
use App\Form\RendezVousType;
use App\Repository\MedecinRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RDVController extends AbstractController
{
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

            $rdvDateTime = new \DateTime();
            $rdvDateTime->setDate(
                $rdv->getDateRdv()->format('Y'),
                $rdv->getDateRdv()->format('m'),
                $rdv->getDateRdv()->format('d')
            );
            $rdvDateTime->setTime(
                $rdv->getHeureRdv()->format('H'),
                $rdv->getHeureRdv()->format('i')
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

        return $this->render('rdv/prendre.html.twig', [
            'form' => $form->createView(),
            'medecins' => $medecinRepository->findBy(['estActif' => true]),
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
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        if ($rdv->getPatient()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier ce rendez-vous');
        }

        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', '❌ Impossible de modifier un rendez-vous annulé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdvDateTime = new \DateTime();
        $rdvDateTime->setDate(
            $rdv->getDateRdv()->format('Y'),
            $rdv->getDateRdv()->format('m'),
            $rdv->getDateRdv()->format('d')
        );
        $rdvDateTime->setTime(
            $rdv->getHeureRdv()->format('H'),
            $rdv->getHeureRdv()->format('i')
        );

        if ($rdvDateTime < new \DateTime()) {
            $this->addFlash('error', '❌ Impossible de modifier un rendez-vous passé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $oldMedecin = $rdv->getMedecin();
        $oldDate = $rdv->getDateRdv();
        $oldHeure = $rdv->getHeureRdv();

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

            $newRdvDateTime = new \DateTime();
            $newRdvDateTime->setDate(
                $rdv->getDateRdv()->format('Y'),
                $rdv->getDateRdv()->format('m'),
                $rdv->getDateRdv()->format('d')
            );
            $newRdvDateTime->setTime(
                $rdv->getHeureRdv()->format('H'),
                $rdv->getHeureRdv()->format('i')
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

    /**
     * 📅 Liste des rendez-vous du patient
     */
    #[Route('/rdv/mes-rdv', name: 'app_rdv_mes_rdv')]
    #[IsGranted('ROLE_PATIENT')]
    public function mesRendezVous(
        RendezVousRepository $rdvRepository
    ): Response {
        $patient = $this->getUser();

        if (!$patient instanceof Patient) {
            throw $this->createAccessDeniedException();
        }

        $rdvs = $rdvRepository->findBy(
            ['patient' => $patient],
            ['dateRdv' => 'DESC', 'heureRdv' => 'DESC']
        );

        return $this->render('rdv/mes_rdv.html.twig', [
            'rendez_vous' => $rdvs,
        ]);
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
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        if ($rdv->getPatient()->getId() !== $this->getUser()->getId()) {
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
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        if ($rdv->getPatient()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', '❌ Ce rendez-vous est déjà annulé');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }

        $rdvDateTime = new \DateTime();
        $rdvDateTime->setDate(
            $rdv->getDateRdv()->format('Y'),
            $rdv->getDateRdv()->format('m'),
            $rdv->getDateRdv()->format('d')
        );
        $rdvDateTime->setTime(
            $rdv->getHeureRdv()->format('H'),
            $rdv->getHeureRdv()->format('i')
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
}
