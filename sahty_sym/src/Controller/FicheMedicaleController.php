<?php

namespace App\Controller;

use App\Form\FicheMedicaleType;
use App\Entity\FicheMedicale;
use App\Entity\Patient;
use App\Entity\Medecin;
use App\Entity\RendezVous;
use App\Repository\FicheMedicaleRepository;
use App\Repository\PatientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/fiche-medicale')]
class FicheMedicaleController extends AbstractController
{
    /**
     * 📋 Page principale - Gère tout dans une page avec permissions
     */
    #[Route('/', name: 'app_fiche_medicale_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        FicheMedicaleRepository $ficheMedicaleRepository,
        PatientRepository $patientRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        $user = $this->getUser();
        $isPatient = $this->isGranted('ROLE_PATIENT');
        $isMedecin = $this->isGranted('ROLE_MEDECIN');
        
        // Mode par défaut : LISTE
        $mode = 'list';
        $fiche = null;
        $form = null;
        
        // ✅ RÉCUPÉRATION DES FICHES SELON LE RÔLE (utilise le repository)
        $fiches = $ficheMedicaleRepository->findByUserRole($user);
        
        // ============ RECHERCHE PAR ID ============
        if ($request->query->has('search_id')) {
            $searchId = $request->query->get('search_id');
            if (!empty($searchId)) {
                $fiche = $ficheMedicaleRepository->find($searchId);
                
                // ✅ Vérifier les permissions d'accès
                if ($fiche) {
                    if ($isPatient && $fiche->getPatient()->getId() !== $user->getId()) {
                        $this->addFlash('error', '❌ Accès non autorisé à cette fiche');
                        return $this->redirectToRoute('app_fiche_medicale_index');
                    }
                    
                    if ($isMedecin) {
                        $hasAccess = $entityManager->getRepository(RendezVous::class)
                            ->createQueryBuilder('r')
                            ->where('r.medecin = :medecin')
                            ->andWhere('r.patient = :patient')
                            ->setParameter('medecin', $user)
                            ->setParameter('patient', $fiche->getPatient())
                            ->setMaxResults(1)
                            ->getQuery()
                            ->getOneOrNullResult();
                        
                        if (!$hasAccess) {
                            $this->addFlash('error', '❌ Vous n\'avez pas accès à cette fiche (aucun RDV avec ce patient)');
                            return $this->redirectToRoute('app_fiche_medicale_index');
                        }
                    }
                    
                    return $this->redirectToRoute('app_fiche_medicale_index', ['view' => $fiche->getId()]);
                } else {
                    $this->addFlash('error', '❌ Aucune fiche trouvée avec l\'ID: ' . $searchId);
                }
            }
        }
        
        // ============ CRÉATION D'UNE NOUVELLE FICHE (Patient uniquement) ============
        if ($request->query->has('new')) {
            if (!$isPatient) {
                $this->addFlash('error', '❌ Seuls les patients peuvent créer des fiches médicales');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
            
            $mode = 'new';
            $fiche = new FicheMedicale();
            $fiche->setPatient($user);
            
            $form = $this->createForm(FicheMedicaleType::class, $fiche, [
                'is_medecin' => false
            ]);
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid()) {
                if (!$fiche->getStatut()) {
                    $fiche->setStatut('actif');
                }
                
                // Calculer l'IMC
                $fiche->calculerImc();
                
                $entityManager->persist($fiche);
                $entityManager->flush();
                
                $this->addFlash('success', '✅ Fiche médicale créée avec succès !');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
        }
        
        // ============ AFFICHAGE DÉTAILLÉ D'UNE FICHE ============
        if ($request->query->has('view')) {
            $mode = 'view';
            $ficheId = $request->query->get('view');
            $fiche = $ficheMedicaleRepository->find($ficheId);
            
            if (!$fiche) {
                $this->addFlash('error', '❌ Fiche non trouvée !');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
            
            // ✅ Vérifier les permissions d'accès
            if ($isPatient && $fiche->getPatient()->getId() !== $user->getId()) {
                $this->addFlash('error', '❌ Accès non autorisé à cette fiche');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
            
            if ($isMedecin) {
                $hasAccess = $entityManager->getRepository(RendezVous::class)
                    ->createQueryBuilder('r')
                    ->where('r.medecin = :medecin')
                    ->andWhere('r.patient = :patient')
                    ->setParameter('medecin', $user)
                    ->setParameter('patient', $fiche->getPatient())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if (!$hasAccess) {
                    $this->addFlash('error', '❌ Vous n\'avez pas accès à cette fiche (aucun RDV avec ce patient)');
                    return $this->redirectToRoute('app_fiche_medicale_index');
                }
            }
            
            // Recalculer l'IMC si nécessaire
            if (!$fiche->getImc() && $fiche->getTaille() && $fiche->getPoids()) {
                $fiche->calculerImc();
                $entityManager->flush();
            }
        }
        
        // ============ MODIFICATION D'UNE FICHE ============
        if ($request->query->has('edit')) {
            $mode = 'edit';
            $ficheId = $request->query->get('edit');
            $fiche = $ficheMedicaleRepository->find($ficheId);
            
            if (!$fiche) {
                $this->addFlash('error', '❌ Fiche non trouvée !');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
            
            // ✅ Vérifier les permissions de modification
            if ($isPatient && $fiche->getPatient()->getId() !== $user->getId()) {
                $this->addFlash('error', '❌ Vous ne pouvez modifier que vos propres fiches');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
            
            if ($isMedecin) {
                $hasAccess = $entityManager->getRepository(RendezVous::class)
                    ->createQueryBuilder('r')
                    ->where('r.medecin = :medecin')
                    ->andWhere('r.patient = :patient')
                    ->setParameter('medecin', $user)
                    ->setParameter('patient', $fiche->getPatient())
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                if (!$hasAccess) {
                    $this->addFlash('error', '❌ Vous n\'avez pas accès à cette fiche (aucun RDV avec ce patient)');
                    return $this->redirectToRoute('app_fiche_medicale_index');
                }
            }
            
            $form = $this->createForm(FicheMedicaleType::class, $fiche, [
                'is_medecin' => $isMedecin
            ]);
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid()) {
                // Recalculer l'IMC
                $fiche->calculerImc();
                
                $entityManager->flush();
                $this->addFlash('success', '✅ Fiche médicale modifiée avec succès !');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
        }
        
        // ============ SUPPRESSION D'UNE FICHE ============
        if ($request->isMethod('POST') && $request->request->has('delete_id')) {
            $ficheId = $request->request->get('delete_id');
            $fiche = $ficheMedicaleRepository->find($ficheId);
            
            if ($fiche && $this->isCsrfTokenValid('delete'.$ficheId, $request->request->get('_token'))) {
                // ✅ Vérifier les permissions de suppression
                if ($isPatient && $fiche->getPatient()->getId() !== $user->getId()) {
                    $this->addFlash('error', '❌ Vous ne pouvez supprimer que vos propres fiches');
                    return $this->redirectToRoute('app_fiche_medicale_index');
                }
                
                if ($isMedecin) {
                    $this->addFlash('error', '❌ Les médecins ne peuvent pas supprimer les fiches médicales');
                    return $this->redirectToRoute('app_fiche_medicale_index');
                }
                
                $entityManager->remove($fiche);
                $entityManager->flush();
                $this->addFlash('success', '✅ Fiche médicale supprimée avec succès !');
            } else {
                $this->addFlash('error', '❌ Token CSRF invalide ou fiche non trouvée !');
            }
            
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        // ✅ Recherche textuelle avec permissions (utilise le repository)
        if ($request->query->has('search')) {
            $searchTerm = $request->query->get('search');
            if (!empty($searchTerm)) {
                $fiches = $ficheMedicaleRepository->searchByText($searchTerm, $user);
            }
        }
        
        return $this->render('fiche_medicale/index.html.twig', [
            'fiches' => $fiches,
            'mode' => $mode,
            'fiche' => $fiche,
            'form' => $form ? $form->createView() : null,
            'isPatient' => $isPatient,
            'isMedecin' => $isMedecin,
        ]);
    }
    
    /**
     * 📄 EXPORT PDF - UNE SEULE FICHE
     */
    #[Route('/export-pdf/{id}', name: 'app_fiche_medicale_export_pdf', methods: ['GET'])]
    public function exportPdf(
        int $id,
        FicheMedicaleRepository $ficheMedicaleRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $fiche = $ficheMedicaleRepository->find($id);
        $user = $this->getUser();
        
        if (!$fiche) {
            $this->addFlash('error', '❌ Fiche non trouvée !');
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        // ✅ Vérifier les permissions
        $isPatient = $this->isGranted('ROLE_PATIENT');
        $isMedecin = $this->isGranted('ROLE_MEDECIN');
        
        if ($isPatient && $fiche->getPatient()->getId() !== $user->getId()) {
            $this->addFlash('error', '❌ Accès non autorisé');
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        if ($isMedecin) {
            $hasAccess = $entityManager->getRepository(RendezVous::class)
                ->createQueryBuilder('r')
                ->where('r.medecin = :medecin')
                ->andWhere('r.patient = :patient')
                ->setParameter('medecin', $user)
                ->setParameter('patient', $fiche->getPatient())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$hasAccess) {
                $this->addFlash('error', '❌ Accès non autorisé (aucun RDV avec ce patient)');
                return $this->redirectToRoute('app_fiche_medicale_index');
            }
        }
        
        // Recalculer l'IMC si nécessaire
        if (!$fiche->getImc() && $fiche->getTaille() && $fiche->getPoids()) {
            $fiche->calculerImc();
        }
        
        return $this->render('fiche_medicale/pdf_single.html.twig', [
            'fiche' => $fiche,
        ]);
    }
    
    /**
     * 📄 EXPORT PDF - TOUTES LES FICHES
     */
    #[Route('/export-all-pdf', name: 'app_fiche_medicale_export_all_pdf', methods: ['GET'])]
    public function exportAllPdf(
        FicheMedicaleRepository $ficheMedicaleRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        // ✅ Récupérer les fiches selon les permissions (utilise le repository)
        $fiches = $ficheMedicaleRepository->findByUserRole($user);
        
        if (empty($fiches)) {
            $this->addFlash('error', '❌ Aucune fiche à exporter');
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        // Calculer l'IMC pour toutes les fiches
        foreach ($fiches as $fiche) {
            if (!$fiche->getImc() && $fiche->getTaille() && $fiche->getPoids()) {
                $fiche->calculerImc();
            }
        }
        
        return $this->render('fiche_medicale/pdf_all.html.twig', [
            'fiches' => $fiches,
        ]);
    }
    
    /**
     * 🔍 AJAX SEARCH avec permissions - CORRIGÉ
     */
    /**
 * 🔍 AJAX SEARCH avec permissions - CORRIGÉ
 */
#[Route('/search-ajax', name: 'app_fiche_medicale_search_ajax', methods: ['GET'])]
public function searchAjax(
    Request $request,
    FicheMedicaleRepository $repository
): JsonResponse {
    $query = $request->query->get('q', '');
    $user = $this->getUser();
    
    if (strlen($query) < 2) {
        return $this->json([]);
    }

    try {
        // ✅ Utilise la méthode du repository
        $fiches = $repository->searchWithPermissions($query, $user);

        $results = [];
        foreach ($fiches as $fiche) {
            $patient = $fiche->getPatient();
            $results[] = [
                'id' => $fiche->getId(),
                'patient' => $patient ? $patient->getNom() . ' ' . $patient->getPrenom() : 'Patient inconnu',
                'statut' => $fiche->getStatut() ?? 'Non défini',
                'creeLe' => $fiche->getCreeLe() ? $fiche->getCreeLe()->format('d/m/Y') : '',
                'imc' => $fiche->getImc() ? number_format($fiche->getImc(), 2) : null,
                'categorieImc' => $fiche->getCategorieImc(),
                'diagnostic' => $fiche->getDiagnostic() ? substr($fiche->getDiagnostic(), 0, 60) . '...' : '',
            ];
        }

        return $this->json($results);
    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => $e->getMessage()
        ], 500);
    }
}

    
    /**
     * ✅ REDIRECTIONS pour les anciennes routes
     */
    #[Route('/new', name: 'app_fiche_medicale_new', methods: ['GET', 'POST'])]
    public function newRedirect(): Response
    {
        return $this->redirectToRoute('app_fiche_medicale_index', ['new' => true]);
    }
    
    #[Route('/{id}', name: 'app_fiche_medicale_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showRedirect($id): Response
    {
        return $this->redirectToRoute('app_fiche_medicale_index', ['view' => $id]);
    }
    
    #[Route('/{id}/edit', name: 'app_fiche_medicale_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editRedirect($id): Response
    {
        return $this->redirectToRoute('app_fiche_medicale_index', ['edit' => $id]);
    }
    
    /**
     * 📝 Route pour créer une fiche avec un patient spécifique
     */
    #[Route('/new-for-patient/{patientId}', name: 'app_fiche_medicale_new_for_patient', methods: ['GET', 'POST'])]
    public function newForPatient(
        int $patientId,
        Request $request,
        PatientRepository $patientRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $patient = $patientRepository->find($patientId);
        
        if (!$patient) {
            $this->addFlash('error', '❌ Patient non trouvé !');
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        // Vérifier les permissions
        $user = $this->getUser();
        if ($user instanceof Patient && $user->getId() !== $patient->getId()) {
            $this->addFlash('error', '❌ Vous ne pouvez créer une fiche que pour vous-même');
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        $fiche = new FicheMedicale();
        $fiche->setPatient($patient);
        
        $form = $this->createForm(FicheMedicaleType::class, $fiche, [
            'is_medecin' => $this->isGranted('ROLE_MEDECIN')
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$fiche->getStatut()) {
                $fiche->setStatut('actif');
            }
            
            // Calculer l'IMC
            $fiche->calculerImc();
            
            $entityManager->persist($fiche);
            $entityManager->flush();
            
            $this->addFlash('success', '✅ Fiche médicale créée pour ' . $patient->getNom() . ' ' . $patient->getPrenom() . ' !');
            return $this->redirectToRoute('app_fiche_medicale_index');
        }
        
        return $this->render('fiche_medicale/index.html.twig', [
            'fiches' => [],
            'mode' => 'new',
            'fiche' => $fiche,
            'form' => $form->createView(),
            'isPatient' => $this->isGranted('ROLE_PATIENT'),
            'isMedecin' => $this->isGranted('ROLE_MEDECIN'),
        ]);
    }
    
    /**
     * 📝 Route pour créer une fiche depuis un rendez-vous
     */
    #[Route('/new-for-rdv/{rdvId}', name: 'app_fiche_medicale_new_for_rdv', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PATIENT')]
    public function newForRdv(
        Request $request,
        int $rdvId,
        EntityManagerInterface $entityManager
    ): Response {
        // Récupérer le rendez-vous
        $rdv = $entityManager->getRepository(RendezVous::class)->find($rdvId);
        
        if (!$rdv) {
            $this->addFlash('error', '❌ Rendez-vous non trouvé !');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }
        
        // Vérifier que l'utilisateur est bien le patient du rendez-vous
        if ($this->getUser()->getId() !== $rdv->getPatient()->getId()) {
            $this->addFlash('error', '❌ Accès non autorisé !');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }
        
        // Vérifier si une fiche existe déjà pour ce rendez-vous
        if ($rdv->getFicheMedicale()) {
            $this->addFlash('info', 'ℹ️ Une fiche médicale existe déjà pour ce rendez-vous.');
            return $this->redirectToRoute('app_fiche_medicale_index', ['view' => $rdv->getFicheMedicale()->getId()]);
        }
        
        // Créer une nouvelle fiche
        $fiche = new FicheMedicale();
        $fiche->setPatient($rdv->getPatient());
        
        $form = $this->createForm(FicheMedicaleType::class, $fiche, [
            'is_medecin' => false
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$fiche->getStatut()) {
                $fiche->setStatut('actif');
            }
            
            // Calculer l'IMC
            $fiche->calculerImc();
            
            // Associer la fiche au rendez-vous
            $rdv->setFicheMedicale($fiche);
            
            // Sauvegarder
            $entityManager->persist($fiche);
            $entityManager->flush();
            
            $this->addFlash('success', '✅ Fiche médicale créée avec succès et associée à votre rendez-vous !');
            return $this->redirectToRoute('app_rdv_mes_rdv');
        }
        
        return $this->render('fiche_medicale/index.html.twig', [
            'fiches' => [],
            'mode' => 'new',
            'fiche' => $fiche,
            'form' => $form->createView(),
            'isPatient' => true,
            'isMedecin' => false,
            'isFromRdv' => true,
            'rdv' => $rdv,
        ]);
    }
}
