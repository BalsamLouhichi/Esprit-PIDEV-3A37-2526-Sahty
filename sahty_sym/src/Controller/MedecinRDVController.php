<?php

namespace App\Controller;

use App\Entity\Medecin;
use App\Entity\RendezVous;
use App\Entity\FicheMedicale;
use App\Form\FicheMedicaleType;
use App\Repository\RendezVousRepository;
use App\Repository\FicheMedicaleRepository;
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
     * 📅 Liste des rendez-vous du médecin avec recherche et filtrage
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

        // Récupérer les paramètres de recherche et filtrage
        $statutFiltre = $request->query->get('statut', 'tous');
        $searchTerm = $request->query->get('search', '');

        // Construction de la requête de base
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

        // ✅ Recherche textuelle (nom, prénom, raison)
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
     * ✅ Confirmer un rendez-vous
     */
    #[Route('/confirmer/{id}', name: 'app_medecin_rdv_confirmer', methods: ['POST'])]
    public function confirmer(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em
    ): Response {
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        // Vérifier que c'est le médecin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le RDV est en attente
        if ($rdv->getStatut() !== 'en attente') {
            $this->addFlash('error', '❌ Ce rendez-vous ne peut pas être confirmé');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // Confirmer
        $rdv->setStatut('Confirmé');
        $rdv->setDateValidation(new \DateTime());
        $em->flush();

        $this->addFlash('success', '✅ Rendez-vous confirmé avec succès');
        return $this->redirectToRoute('app_medecin_rdv_liste');
    }

    /**
     * ❌ Annuler un rendez-vous
     */
    #[Route('/annuler/{id}', name: 'app_medecin_rdv_annuler', methods: ['POST'])]
    public function annuler(
        int $id,
        RendezVousRepository $rdvRepository,
        EntityManagerInterface $em
    ): Response {
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        // Vérifier que c'est le médecin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que le RDV n'est pas déjà annulé
        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', '❌ Ce rendez-vous est déjà annulé');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // Annuler
        $rdv->setStatut('Annulé');
        $em->flush();

        $this->addFlash('success', '✅ Rendez-vous annulé avec succès');
        return $this->redirectToRoute('app_medecin_rdv_liste');
    }

    /**
     * 👁️ Voir les détails d'un rendez-vous
     */
    #[Route('/details/{id}', name: 'app_medecin_rdv_details', methods: ['GET'])]
    public function details(
        int $id,
        RendezVousRepository $rdvRepository
    ): Response {
        $rdv = $rdvRepository->find($id);

        if (!$rdv) {
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        // Vérifier que c'est le médecin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('medecin/rdv/details.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    /**
     * 📝 Compléter/Modifier la fiche médicale d'un patient
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
            throw $this->createNotFoundException('Rendez-vous non trouvé');
        }

        // Vérifier que c'est le médecin du RDV
        if ($rdv->getMedecin()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Vérifier que le RDV n'est pas annulé
        if ($rdv->getStatut() === 'Annulé') {
            $this->addFlash('error', '❌ Impossible de créer/modifier une fiche médicale pour un rendez-vous annulé');
            return $this->redirectToRoute('app_medecin_rdv_liste');
        }

        // Récupérer ou créer la fiche médicale
        $fiche = $rdv->getFicheMedicale();
        $isNew = false;

        if (!$fiche) {
            // Créer une nouvelle fiche
            $fiche = new FicheMedicale();
            $fiche->setPatient($rdv->getPatient());
            $fiche->setCreeLe(new \DateTime());
            $fiche->setStatut('actif');
            $isNew = true;
        }

        // Formulaire avec permissions médecin
        $form = $this->createForm(FicheMedicaleType::class, $fiche, [
            'is_medecin' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculer l'IMC si nécessaire
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
                    $fiche->setCategorieImc('Obésité');
                }
            }

            if ($isNew) {
                $rdv->setFicheMedicale($fiche);
                $em->persist($fiche);
            } else {
                $fiche->setModifieLe(new \DateTime());
            }

            $em->flush();

            $this->addFlash('success', '✅ Fiche médicale enregistrée avec succès');
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
