<?php
// src/Controller/DemandeAnalyseController.php

namespace App\Controller;

use App\Entity\DemandeAnalyse;
use App\Entity\Patient;
use App\Entity\Laboratoire;
use App\Entity\Medecin;
use App\Entity\ResponsableLaboratoire;
use App\Form\DemandeAnalyseType;
use App\Repository\DemandeAnalyseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/demande-analyse')]
class DemandeAnalyseController extends AbstractController
{
    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Récupère l'utilisateur connecté ou un utilisateur statique par défaut pour les tests
     */
    private function getTestUser(): ?UserInterface
    {
        $user = $this->getUser();
        
        // Si un utilisateur est connecté, on l'utilise
        if ($user instanceof UserInterface) {
            return $user;
        }
        
        // Sinon, on utilise un utilisateur statique pour les tests
        // Essayons d'abord de trouver un patient avec ID 1
        $testPatient = $this->entityManager->getRepository(Patient::class)->find(1);
        if ($testPatient) {
            return $testPatient;
        }
        
        // Si aucun patient ID 1, prenons le premier patient disponible
        $testPatient = $this->entityManager->getRepository(Patient::class)->findOneBy([]);
        if ($testPatient) {
            return $testPatient;
        }
        
        // Si aucun patient, essayons avec un médecin
        $testMedecin = $this->entityManager->getRepository(Medecin::class)->findOneBy([]);
        if ($testMedecin) {
            return $testMedecin;
        }
        
        return null;
    }
    
    /**
     * Vérifie si on est en mode test (pas d'utilisateur connecté)
     */
    private function isTestMode(): bool
    {
        return !$this->getUser();
    }

    /**
     * Liste des demandes d'analyse (pour administrateurs et médecins)
     */
    #[Route('/', name: 'app_demande_analyse_index', methods: ['GET'])]
    public function index(DemandeAnalyseRepository $demandeAnalyseRepository): Response
    {
        $user = $this->getTestUser();
        
        // Mode test: on simule un rôle patient
        if ($this->isTestMode()) {
            // En mode test, on utilise la vue patient
            return $this->redirectToRoute('app_demande_analyse_mes_demandes');
        }

        // Si l'utilisateur est un patient, rediriger vers ses propres demandes
        if ($user instanceof Patient) {
            return $this->redirectToRoute('app_demande_analyse_mes_demandes');
        }

        // Pour les responsables de laboratoire, voir seulement les demandes du labo
        if ($user instanceof ResponsableLaboratoire) {
            $laboratoire = $user->getLaboratoire();
            $demandes = $laboratoire
                ? $demandeAnalyseRepository->findBy(['laboratoire' => $laboratoire])
                : [];

            return $this->render('responsable_laboratoire/demandes.html.twig', [
                'demande_analyses' => $demandes,
                'controller_name' => 'DemandeAnalyseController',
                'test_mode' => $this->isTestMode(),
            ]);
        }

        // Pour les médecins, voir seulement leurs propres demandes
        if ($user instanceof Medecin) {
            $demandes = $demandeAnalyseRepository->findBy(['medecin' => $user]);
        } else {
            // Pour les admins, voir toutes les demandes
            $demandes = $demandeAnalyseRepository->findAll();
        }

        return $this->render('demande_analyse/index.html.twig', [
            'demande_analyses' => $demandes,
            'controller_name' => 'DemandeAnalyseController',
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Liste des demandes du patient connecté
     */
    #[Route('/mes-demandes', name: 'app_demande_analyse_mes_demandes', methods: ['GET'])]
    public function mesDemandes(Request $request, DemandeAnalyseRepository $demandeAnalyseRepository): Response
    {
        [$demandes, $typeBilanOptions, $typeBilanFilter] = $this->buildMesDemandesData($request, $demandeAnalyseRepository);

        return $this->render('demande_analyse/mes_demandes.html.twig', [
            'demandes' => $demandes,
            'type_bilan_filter' => $typeBilanFilter,
            'type_bilan_options' => $typeBilanOptions,
            'test_mode' => $this->isTestMode(),
        ]);
    }

    #[Route('/mes-demandes/filter', name: 'app_demande_analyse_mes_demandes_filter', methods: ['GET'])]
    public function mesDemandesFilter(Request $request, DemandeAnalyseRepository $demandeAnalyseRepository): JsonResponse
    {
        [$demandes, $typeBilanOptions, $typeBilanFilter] = $this->buildMesDemandesData($request, $demandeAnalyseRepository);

        $html = $this->renderView('demande_analyse/_mes_demandes_results.html.twig', [
            'demandes' => $demandes,
        ]);

        $countText = $demandes ? sprintf('%d demande(s) trouvée(s)', count($demandes)) : 'Aucune demande pour le moment';

        return $this->json([
            'html' => $html,
            'count_text' => $countText,
        ]);
    }

    private function buildMesDemandesData(Request $request, DemandeAnalyseRepository $demandeAnalyseRepository): array
    {
        $user = $this->getTestUser();

        if (!$user instanceof Patient) {
            if ($this->isTestMode()) {
                $user = $this->entityManager->getRepository(Patient::class)->findOneBy([]);
                if (!$user) {
                    throw new AccessDeniedException('Aucun patient trouvé pour le test.');
                }
            } else {
                throw new AccessDeniedException('Accès réservé aux patients.');
            }
        }

        $allDemandes = $demandeAnalyseRepository->findBy(
            ['patient' => $user],
            ['programme_le' => 'DESC']
        );

        $typeBilanFilter = trim((string) $request->query->get('type_bilan', ''));
        $demandes = $allDemandes;

        if ($typeBilanFilter !== '') {
            $demandes = array_values(array_filter(
                $allDemandes,
                static fn (DemandeAnalyse $demande) => $demande->getTypeBilan() === $typeBilanFilter
            ));
        }

        $typeBilanOptions = [];
        foreach ($allDemandes as $demande) {
            $type = $demande->getTypeBilan();
            if ($type) {
                $typeBilanOptions[$type] = $type;
            }
        }
        ksort($typeBilanOptions);

        return [$demandes, $typeBilanOptions, $typeBilanFilter];
    }

    /**
     * Créer une nouvelle demande d'analyse pour un laboratoire spécifique
     */
    #[Route('/new/{laboratoireId}', name: 'app_demande_analyse_new_for_lab', methods: ['GET', 'POST'])]
    public function newForLab(
        Request $request, 
        EntityManagerInterface $entityManager,
        int $laboratoireId
    ): Response
    {
        $laboratoire = $entityManager->getRepository(Laboratoire::class)->find($laboratoireId);
        
        if (!$laboratoire) {
            throw $this->createNotFoundException('Laboratoire non trouvé.');
        }

        $responsable = $laboratoire->getResponsable();
        if (!$laboratoire->isDisponible() || ($responsable && !$responsable->isEstActif())) {
            $this->addFlash('error', 'Ce laboratoire est indisponible pour le moment.');
            return $this->redirectToRoute('app_labo_show', ['id' => $laboratoireId]);
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Si c'est une requête POST (formulaire simple)
        if ($request->isMethod('POST')) {
            // Vérifier le token CSRF
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('demande_analyse_new', $submittedToken)) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_labo_show', ['id' => $laboratoireId]);
            }
            
            $demandeAnalyse = new DemandeAnalyse();
            
            // Définir le laboratoire
            $demandeAnalyse->setLaboratoire($laboratoire);
            
            // Définir le type de bilan
            $typeAnalyse = $request->request->get('type_analyse');
            $demandeAnalyse->setTypeBilan($typeAnalyse ?? 'Analyse non spécifiée');
            
            // Associer le patient
            $user = $this->getUser();
            if ($user instanceof Patient) {
                $demandeAnalyse->setPatient($user);
                // IMPORTANT : Ne pas définir de médecin automatiquement
                // Le médecin reste null (optionnel)
            }
            
            // NOTE IMPORTANTE : On ne définit PAS de médecin automatiquement
            // Le champ medecin est optionnel et reste null

            $medecinId = $request->request->get('medecin_id');
            if ($medecinId) {
                $medecin = $entityManager->getRepository(Medecin::class)->find($medecinId);
                if ($medecin) {
                    $demandeAnalyse->setMedecin($medecin);
                }
            }
            
            // Ajouter les notes
            $notes = $request->request->get('notes');
            $dateSouhaitee = $request->request->get('date_souhaitee');
            $heureSouhaitee = $request->request->get('heure_souhaitee');
            
            $notesCompletes = "Date souhaitée: " . $dateSouhaitee . " à " . $heureSouhaitee;
            if ($notes) {
                $notesCompletes .= "\n" . $notes;
            }
            
            // Informations de contact supplémentaires
            $nom = $request->request->get('nom');
            $telephone = $request->request->get('telephone');
            $email = $request->request->get('email');
            
            if ($nom || $telephone || $email) {
                $notesCompletes .= "\n\n--- Informations de contact ---";
                if ($nom) $notesCompletes .= "\nNom: " . $nom;
                if ($telephone) $notesCompletes .= "\nTéléphone: " . $telephone;
                if ($email) $notesCompletes .= "\nEmail: " . $email;
            }
            
            $demandeAnalyse->setNotes($notesCompletes);
            
            // Si date programmée fournie
            if ($dateSouhaitee && $heureSouhaitee) {
                try {
                    $programmeLe = new \DateTime($dateSouhaitee . ' ' . $heureSouhaitee);
                    $demandeAnalyse->setProgrammeLe($programmeLe);
                } catch (\Exception $e) {
                    // Ignorer l'erreur de date
                }
            }
            
            // Définir la date de création
            $demandeAnalyse->setDateDemande(new \DateTimeImmutable());
            
            // Définir le statut par défaut
            $demandeAnalyse->setStatut('en_attente');
            
            $entityManager->persist($demandeAnalyse);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande d\'analyse a été créée avec succès.');

            // Rediriger selon le type d'utilisateur
            if ($user instanceof Patient || $this->isTestMode()) {
                return $this->redirectToRoute('app_demande_analyse_mes_demandes');
            } else {
                return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
            }
        }

        // Si c'est une requête GET, afficher le formulaire Symfony complet
        $demandeAnalyse = new DemandeAnalyse();
        $demandeAnalyse->setLaboratoire($laboratoire);
        
        $user = $this->getUser();
        
        if ($user instanceof Patient) {
            $demandeAnalyse->setPatient($user);
            // IMPORTANT : Ne pas définir de médecin automatiquement
        }

        $userRole = $user ? $user->getRoles()[0] : 'ROLE_PATIENT';
        
        $form = $this->createForm(DemandeAnalyseType::class, $demandeAnalyse, [
            'user_role' => $userRole,
            'user_entity' => $user,
            'laboratoire' => $laboratoire,
        ]);
        
        return $this->render('demande_analyse/new.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'form' => $form->createView(),
            'laboratoire' => $laboratoire,
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Afficher les détails d'une demande d'analyse
     */
    #[Route('/{id}', name: 'app_demande_analyse_show', methods: ['GET'])]
    public function show(DemandeAnalyse $demandeAnalyse): Response
    {
        // En mode test, on autorise l'accès sans vérification stricte
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }

        return $this->render('demande_analyse/show.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Modifier une demande d'analyse
     */
    #[Route('/{id}/edit', name: 'app_demande_analyse_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        DemandeAnalyse $demandeAnalyse, 
        EntityManagerInterface $entityManager
    ): Response
    {
        // En mode test, on autorise l'accès sans vérification stricte
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }
        
        // Empêcher la modification si un resultat PDF existe
        if ($demandeAnalyse->getResultatPdf()) {
            $this->addFlash('warning', 'Cette demande a déjà un resultat et ne peut plus être modifiée.');
            return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
        }
        

        $user = $this->getTestUser();
        $userRole = $user ? $user->getRoles()[0] : 'ROLE_PATIENT';

        $form = $this->createForm(DemandeAnalyseType::class, $demandeAnalyse, [
            'user_role' => $userRole,
            'user_entity' => $user,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Mettre à jour la date de modification
                // Vous pouvez ajouter un champ date_modification si nécessaire
                
                $entityManager->flush();

                $this->addFlash('success', 'Demande d\'analyse mise à jour avec succès.');

                if ($user instanceof Patient || $this->isTestMode()) {
                    return $this->redirectToRoute('app_demande_analyse_mes_demandes');
                } else {
                    return $this->redirectToRoute('app_demande_analyse_index');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('demande_analyse/edit.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'form' => $form->createView(),
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Supprimer une demande d'analyse
     */
    #[Route('/{id}/delete', name: 'app_demande_analyse_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        DemandeAnalyse $demandeAnalyse, 
        EntityManagerInterface $entityManager
    ): Response
    {
        // En mode test, on autorise l'accès sans vérification stricte
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }
        
        // Empêcher la suppression si un resultat PDF existe
        if ($demandeAnalyse->getResultatPdf()) {
            $this->addFlash('warning', 'Cette demande a déjà un resultat et ne peut plus être supprimée.');
            
            $user = $this->getTestUser();
            if ($user instanceof Patient || $this->isTestMode()) {
                return $this->redirectToRoute('app_demande_analyse_mes_demandes');
            } else {
                return $this->redirectToRoute('app_demande_analyse_index');
            }
        }
        

        if ($this->isCsrfTokenValid('delete'.$demandeAnalyse->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($demandeAnalyse);
                $entityManager->flush();
                
                $this->addFlash('success', 'Demande d\'analyse supprimée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        $user = $this->getTestUser();
        if ($user instanceof Patient || $this->isTestMode()) {
            return $this->redirectToRoute('app_demande_analyse_mes_demandes');
        } else {
            return $this->redirectToRoute('app_demande_analyse_index');
        }
    }

    /**
     * Changer le statut d'une demande d'analyse (pour médecins et administrateurs)
     */
    #[Route('/{id}/changer-statut/{statut}', name: 'app_demande_analyse_changer_statut', methods: ['POST'])]
    public function changerStatut(
        Request $request,
        DemandeAnalyse $demandeAnalyse,
        string $statut,
        EntityManagerInterface $entityManager
    ): Response
    {
        // En mode test, on autorise tout le monde
        if (!$this->isTestMode()) {
            // Seuls les médecins et administrateurs peuvent changer le statut
            if (!$this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedException('Vous n\'avez pas la permission de modifier le statut.');
            }
        }

        if ($this->isCsrfTokenValid('changer-statut'.$demandeAnalyse->getId(), $request->request->get('_token'))) {
            $statutsValides = ['en_attente', 'envoye'];

            if (!in_array($statut, $statutsValides, true)) {
                $this->addFlash('error', 'Statut invalide.');
                return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
            }

            $statut = $demandeAnalyse->getResultatPdf() ? 'envoye' : 'en_attente';
            $demandeAnalyse->setStatut($statut);

            // Mettre à jour la date d'envoi si resultat PDF disponible
            if ($statut === 'envoye' && !$demandeAnalyse->getEnvoyeLe()) {
                $demandeAnalyse->setEnvoyeLe(new \DateTime());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Statut de la demande mis à jour avec succès.');
        }

        return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
    }

    /**
     * Vérifier l'accès à une demande d'analyse
     */
    private function checkAccess(DemandeAnalyse $demandeAnalyse): void
    {
        $user = $this->getUser();

        // Les administrateurs ont accès à tout
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Les médecins ne peuvent voir que leurs propres demandes
        if ($this->isGranted('ROLE_MEDECIN') && $user instanceof Medecin) {
            if ($demandeAnalyse->getMedecin() !== $user) {
                throw new AccessDeniedException('Vous n\'avez pas accès à cette demande.');
            }
            return;
        }

        // Les patients ne peuvent voir que leurs propres demandes
        if ($this->isGranted('ROLE_PATIENT') && $user instanceof Patient) {
            if ($demandeAnalyse->getPatient() !== $user) {
                throw new AccessDeniedException('Vous n\'avez pas accès à cette demande.');
            }
            return;
        }

        throw new AccessDeniedException('Accès non autorisé.');
    }

    /**
     * Créer une nouvelle demande d'analyse
     */
    #[Route('/new', name: 'app_demande_analyse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $demandeAnalyse = new DemandeAnalyse();
        
        $user = $this->getTestUser();
        $isTestMode = $this->isTestMode();
        
        if ($isTestMode) {
            // En mode test, chercher des entités par défaut
            if (!$user || !$user instanceof Patient) {
                $patient = $entityManager->getRepository(Patient::class)->findOneBy([]);
                if ($patient) {
                    $demandeAnalyse->setPatient($patient);
                    $user = $patient;
                }
            } else {
                $demandeAnalyse->setPatient($user);
            }
            
            // IMPORTANT : Ne pas chercher un médecin par défaut
            // Le médecin reste null (optionnel)
            
            // Chercher un laboratoire existant pour tester
            $laboratoire = $entityManager->getRepository(Laboratoire::class)->findOneBy([]);
            if ($laboratoire) {
                $demandeAnalyse->setLaboratoire($laboratoire);
            }
        } else {
            // EN PRODUCTION : utiliser l'utilisateur connecté
            if ($user instanceof Patient) {
                $demandeAnalyse->setPatient($user);
                // IMPORTANT : Ne pas définir de médecin automatiquement
            }

            if ($user instanceof Medecin) {
                // Si c'est un médecin qui crée la demande, on l'associe comme médecin
                $demandeAnalyse->setMedecin($user);
            }
        }

        // Déterminer le rôle pour le formulaire
        $userRole = $user ? $user->getRoles()[0] : 'ROLE_PATIENT';

        $form = $this->createForm(DemandeAnalyseType::class, $demandeAnalyse, [
            'user_role' => $userRole,
            'user_entity' => $user,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Si mode test et pas de patient, chercher un patient par défaut
                if ($isTestMode && !$demandeAnalyse->getPatient()) {
                    $defaultPatient = $entityManager->getRepository(Patient::class)->findOneBy([]);
                    if ($defaultPatient) {
                        $demandeAnalyse->setPatient($defaultPatient);
                    } else {
                        throw new \Exception('Aucun patient disponible pour le test.');
                    }
                }
                
                // Vérifications
                if (!$demandeAnalyse->getPatient()) {
                    throw new \Exception('Un patient doit être sélectionné.');
                }
                
                if (!$demandeAnalyse->getLaboratoire()) {
                    throw new \Exception('Un laboratoire doit être sélectionné.');
                }
                
                // Définir la date de création
                $demandeAnalyse->setDateDemande(new \DateTimeImmutable());
                
                // Définir le statut par défaut
                $demandeAnalyse->setStatut('en_attente');
                
                $entityManager->persist($demandeAnalyse);
                $entityManager->flush();

                $this->addFlash('success', 'Demande d\'analyse créée avec succès.');

                // Redirection
                if ($user instanceof Patient || $isTestMode) {
                    return $this->redirectToRoute('app_demande_analyse_mes_demandes');
                } else {
                    return $this->redirectToRoute('app_demande_analyse_index');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }
        }

        return $this->render('demande_analyse/new.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'form' => $form->createView(),
            'test_mode' => $isTestMode,
        ]);
    }

}