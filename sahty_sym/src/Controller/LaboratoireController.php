<?php

namespace App\Controller;

use App\Entity\Laboratoire;
use App\Entity\ResponsableLaboratoire;
use App\Entity\LaboratoireTypeAnalyse;
use App\Form\LaboratoireType;
use App\Repository\LaboratoireRepository;
use App\Repository\MedecinRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/laboratoire')]
class LaboratoireController extends AbstractController
{
    #[Route('/', name: 'app_labo_index', methods: ['GET'])]
    public function index(Request $request, LaboratoireRepository $laboratoireRepository): Response
    {
        $name = trim((string) $request->query->get('name', ''));
        $ville = trim((string) $request->query->get('ville', ''));
        $typeBilan = trim((string) $request->query->get('type_bilan', ''));

        $laboratoires = $laboratoireRepository->findWithPublicFilters(
            $name !== '' ? $name : null,
            $ville !== '' ? $ville : null,
            $typeBilan !== '' ? $typeBilan : null
        );

        $villes = $laboratoireRepository->findDistinctVilles();
        $typeBilans = $laboratoireRepository->findDistinctTypeBilans();

        return $this->render('laboratoire/labo.html.twig', [
            'laboratoires' => $laboratoires,
            'filter_name' => $name,
            'filter_ville' => $ville,
            'filter_type_bilan' => $typeBilan,
            'filter_villes' => $villes,
            'filter_type_bilans' => $typeBilans,
        ]);
    }

    #[Route('/filter', name: 'app_labo_filter', methods: ['GET'])]
    public function filter(Request $request, LaboratoireRepository $laboratoireRepository): Response
    {
        $name = trim((string) $request->query->get('name', ''));
        $ville = trim((string) $request->query->get('ville', ''));
        $typeBilan = trim((string) $request->query->get('type_bilan', ''));

        $laboratoires = $laboratoireRepository->findWithPublicFilters(
            $name !== '' ? $name : null,
            $ville !== '' ? $ville : null,
            $typeBilan !== '' ? $typeBilan : null
        );

        return $this->render('laboratoire/_lab_cards.html.twig', [
            'laboratoires' => $laboratoires,
        ]);
    }

    #[Route('/new', name: 'app_labo_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $laboratoire = new Laboratoire();

        $form = $this->createForm(LaboratoireType::class, $laboratoire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set the current date/time
            $laboratoire->setCreeLe(new \DateTime());

            $user = $this->getUser();
            if ($user instanceof ResponsableLaboratoire) {
                if ($user->getLaboratoire() && $user->getLaboratoire() !== $laboratoire) {
                    $this->addFlash('warning', 'Votre compte etait deja associe a un autre laboratoire. Il a ete remplace.');
                }
                $laboratoire->setResponsable($user);
            }
            
            // Associer le laboratoire à chaque LaboratoireTypeAnalyse
            foreach ($laboratoire->getLaboratoireTypeAnalyses() as $typeAnalyse) {
                $typeAnalyse->setLaboratoire($laboratoire);
            }
            
            $em->persist($laboratoire);
            $em->flush();

            $this->addFlash('success', 'Laboratoire ajouté avec succès ✅');
            if ($user instanceof ResponsableLaboratoire) {
                return $this->redirectToRoute('app_responsable_labo_demandes');
            }

            return $this->redirectToRoute('app_labo_show', ['id' => $laboratoire->getId()]);
        }

        return $this->render('laboratoire/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_labo_show', methods: ['GET'])]
    public function show(Laboratoire $laboratoire, MedecinRepository $medecinRepository): Response
    {
        // Récupérer tous les médecins depuis la base de données
        $medecins = $medecinRepository->findAll();
        
        // Debug: vérifiez ce qui est récupéré
        // dump($medecins);
        
        // Optionnel: trier par nom complet
        usort($medecins, function($a, $b) {
            return strcmp($a->getNomComplet() ?? '', $b->getNomComplet() ?? '');
        });

        // Grouper les analyses par catégorie
        $analysesParCategorie = [];
        $analysesDetailsParCategorie = [];

        foreach ($laboratoire->getLaboratoireTypeAnalyses() as $lta) {
            /** @var LaboratoireTypeAnalyse $lta */
            $type = $lta->getTypeAnalyse();
            if (!$type) continue;

            $cat = $type->getCategorie() ?? 'Autres';

            // Stocker les informations complètes
            $analysesDetailsParCategorie[$cat][] = [
                'type_analyse' => $type,
                'disponible' => $lta->isDisponible(),
                'prix' => $lta->getPrix(),
                'delai' => $lta->getDelaiResultatHeures(),
                'conditions' => $lta->getConditions()
            ];
            
            // Pour compatibilité avec l'ancien code
            $analysesParCategorie[$cat][] = $type;
        }

        // Trier les catégories
        ksort($analysesParCategorie);
        ksort($analysesDetailsParCategorie);

        return $this->render('laboratoire/labo-details.html.twig', [
            'laboratoire' => $laboratoire,
            'analysesParCategorie' => $analysesParCategorie,
            'analysesDetailsParCategorie' => $analysesDetailsParCategorie,
            'medecins' => $medecins, // Passer les médecins au template
        ]);
    }

    #[Route('/{id}/edit', name: 'app_labo_edit', methods: ['GET', 'POST'])]
    public function edit(
        Laboratoire $laboratoire,
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(LaboratoireType::class, $laboratoire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Associer le laboratoire à chaque LaboratoireTypeAnalyse
            foreach ($laboratoire->getLaboratoireTypeAnalyses() as $typeAnalyse) {
                $typeAnalyse->setLaboratoire($laboratoire);
            }
            
            $em->flush();

            $this->addFlash('success', 'Laboratoire modifié avec succès ✅');
            return $this->redirectToRoute('app_labo_show', ['id' => $laboratoire->getId()]);
        }

        return $this->render('laboratoire/new.html.twig', [
            'form' => $form->createView(),
            'laboratoire' => $laboratoire,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_labo_delete', methods: ['POST'])]
    public function delete(Laboratoire $laboratoire, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_labo_' . $laboratoire->getId(), $request->request->get('_token'))) {
            $em->remove($laboratoire);
            $em->flush();
            $this->addFlash('success', 'Laboratoire supprimé ✅');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide ❌');
        }

        return $this->redirectToRoute('app_labo_index');
    }

    #[Route('/search/{term}', name: 'app_labo_search', methods: ['GET'])]
    public function search(string $term, LaboratoireRepository $repo): Response
    {
        $laboratoires = $repo->searchByName($term);

        return $this->render('laboratoire/search.html.twig', [
            'laboratoires' => $laboratoires,
            'search_term' => $term
        ]);
    }

    /**
     * API pour récupérer les médecins (optionnel, pour AJAX)
     */
    #[Route('/api/medecins', name: 'app_api_medecins', methods: ['GET'])]
    public function getMedecinsApi(MedecinRepository $medecinRepository): Response
    {
        $medecins = $medecinRepository->findAll();
        
        $data = [];
        foreach ($medecins as $medecin) {
            $data[] = [
                'id' => $medecin->getId(),
                'nomComplet' => $medecin->getNomComplet(),
                'specialite' => $medecin->getSpecialite(),
                'telephoneCabinet' => $medecin->getTelephoneCabinet(),
                'adresseCabinet' => $medecin->getAdresseCabinet(),
                'nomEtablissement' => $medecin->getNomEtablissement(),
                'grade' => $medecin->getGrade(),
            ];
        }
        
        return $this->json($data);
    }

    /**
     * Test pour vérifier la récupération des médecins
     */
    #[Route('/test/medecins', name: 'app_labo_test_medecins', methods: ['GET'])]
    public function testMedecins(MedecinRepository $medecinRepository): Response
    {
        $medecins = $medecinRepository->findAll();
        
        return $this->render('test/medecins.html.twig', [
            'medecins' => $medecins,
            'count' => count($medecins),
        ]);
    }
}