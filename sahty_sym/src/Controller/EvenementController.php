<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Utilisateur;
use App\Entity\InscriptionEvenement;
use App\Entity\GroupeCible;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\InscriptionEvenementRepository;
use App\Repository\GroupeCibleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/evenements', name: 'evenements_')]
class EvenementController extends AbstractController
{
    private EvenementRepository $evenementRepository;

    public function __construct(EvenementRepository $evenementRepository)
    {
        $this->evenementRepository = $evenementRepository;
    }

    #[Route('/', name: 'evenement_list', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        // Rediriger les non-admins vers la page client
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('evenements_client_events');
        }
        
        // Seuls les admins continuent ici
        $user = $this->getUser();
        $type = $request->query->get('type');
        $statut = $request->query->get('statut');
        $recherche = $request->query->get('recherche');
        $tri = $request->query->get('tri', 'dateDebut');
        $ordre = $request->query->get('ordre', 'ASC');

        $evenements = $this->evenementRepository->findByFilters($type, $statut, $recherche);

        if (empty($type) && empty($statut) && empty($recherche)) {
            $evenements = $this->evenementRepository->getEvenementsTries($tri, $ordre, $statut);
        } else {
            usort($evenements, function($a, $b) use ($tri, $ordre) {
                $valA = $tri === 'dateFin' ? $a->getDateFin() : $a->getDateDebut();
                $valB = $tri === 'dateFin' ? $b->getDateFin() : $b->getDateDebut();
                
                if ($valA == $valB) return 0;
                if ($valA === null) return 1;
                if ($valB === null) return -1;
                
                return ($valA < $valB ? -1 : 1) * ($ordre === 'ASC' ? 1 : -1);
            });
        }

        $statutsDisponibles = $this->evenementRepository->getStatutsDisponibles();

        $totalParticipants = 0;
        $totalTauxRemplissage = 0;
        $evenementsAVenir = 0;
        $now = new \DateTime();

        foreach ($evenements as $evenement) {
            $participants = $this->evenementRepository->getNombreParticipants($evenement);
            $tauxRemplissage = $this->evenementRepository->getTauxRemplissage($evenement);

            $totalParticipants += $participants;
            $totalTauxRemplissage += $tauxRemplissage;

            if ($evenement->getDateDebut() > $now) {
                $evenementsAVenir++;
            }
        }

        $stats = [
            'evenementsAVenir' => $evenementsAVenir,
            'totalParticipants' => $totalParticipants,
            'tauxRemplissageMoyen' => count($evenements) > 0 ? round($totalTauxRemplissage / count($evenements), 1) : 0,
            'revenusGeneres' => 0,
        ];

        $actions = [];
        foreach ($evenements as $evt) {
            $canEdit = true; // Admin peut toujours éditer
            
            $inscriptionsCount = $em->getRepository(InscriptionEvenement::class)->count(['evenement' => $evt]);
            $canDelete = true; // Admin peut toujours supprimer

            $canInscrire = true; // Admin peut toujours voir l'inscription

            $actions[$evt->getId()] = [
                'can_edit' => (bool) $canEdit,
                'can_delete' => (bool) $canDelete,
                'can_inscribe' => (bool) $canInscrire,
            ];
        }

        return $this->render('evenement/evenement.html.twig', [
            'evenements' => $evenements,
            'statutsDisponibles' => $statutsDisponibles,
            'stats' => $stats,
            'tri' => $tri,
            'ordre' => $ordre,
            'type' => $type,
            'statut' => $statut,
            'recherche' => $recherche,
            'is_admin' => true, // Forcément admin ici
            'actions' => $actions,
        ]);
    }

    #[Route('/nouveau', name: 'evenement_add', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $allowedRoles = ['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RESPONSABLE_LABO', 'ROLE_RESPONSABLE_PARA', 'ROLE_PATIENT'];
        $hasPermission = false;
        
        foreach ($allowedRoles as $role) {
            if ($this->isGranted($role)) {
                $hasPermission = true;
                break;
            }
        }
        
        if (!$hasPermission) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de créer des événements.');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $evenement = new Evenement();
        
        if ($this->isGranted('ROLE_PATIENT')) {
            $evenement->setStatut('en_attente_approbation');
        } else {
            $evenement->setStatut('planifie');
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'user_role' => $user->getRoles()[0] ?? 'ROLE_USER',
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = [];
            
            if (!$evenement->getDateDebut()) {
                $errors[] = 'La date de début est obligatoire.';
            }
            
            if (!$evenement->getDateFin()) {
                $errors[] = 'La date de fin est obligatoire.';
            }
            
            if ($evenement->getDateDebut() && $evenement->getDateFin()) {
                if ($evenement->getDateFin() < $evenement->getDateDebut()) {
                    $errors[] = 'La date de fin doit être postérieure à la date de début.';
                }
                
                $now = new \DateTime();
                if ($evenement->getDateDebut() < $now) {
                    $errors[] = 'La date de début ne peut pas être dans le passé.';
                }
                
                $diff = $evenement->getDateDebut()->diff($evenement->getDateFin());
                if ($diff->days > 30) {
                    $errors[] = 'La durée de l\'événement ne peut pas dépasser 30 jours.';
                }
            }
            
            if ($evenement->getPlacesMax() !== null) {
                if ($evenement->getPlacesMax() <= 0) {
                    $errors[] = 'Le nombre de places doit être supérieur à zéro.';
                }
                if ($evenement->getPlacesMax() > 10000) {
                    $errors[] = 'Le nombre de places ne peut pas dépasser 10000.';
                }
            }
            
            if ($evenement->getTarif() !== null) {
                if ($evenement->getTarif() < 0) {
                    $errors[] = 'Le tarif ne peut pas être négatif.';
                }
                if ($evenement->getTarif() > 10000) {
                    $errors[] = 'Le tarif ne peut pas dépasser 10000 TND.';
                }
            }
            
            if (strlen($evenement->getTitre()) < 5) {
                $errors[] = 'Le titre doit contenir au moins 5 caractères.';
            }
            
            if (strlen($evenement->getTitre()) > 200) {
                $errors[] = 'Le titre ne peut pas dépasser 200 caractères.';
            }
            
            if ($evenement->getDescription() && strlen($evenement->getDescription()) > 5000) {
                $errors[] = 'La description ne peut pas dépasser 5000 caractères.';
            }
            
            if ($evenement->getLieu() && strlen($evenement->getLieu()) > 500) {
                $errors[] = 'Le lieu ne peut pas dépasser 500 caractères.';
            }
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('evenement/new.html.twig', [
                    'form' => $form->createView(),
                    'is_patient' => $this->isGranted('ROLE_PATIENT'),
                ]);
            }

            $evenement->setCreeLe(new \DateTime());
            $evenement->setCreateur($user);

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement ajouté avec succès');
            
            if ($this->isGranted('ROLE_PATIENT')) {
                $this->addFlash('info', 'Votre événement est en attente d\'approbation par un administrateur.');
            }

            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('evenements_evenement_list');
            } else {
                return $this->redirectToRoute('evenements_client_events');
            }
        }

        return $this->render('evenement/new.html.twig', [
            'form' => $form->createView(),
            'is_patient' => $this->isGranted('ROLE_PATIENT'),
        ]);
    }

    #[Route('/{id}/modifier', name: 'evenement_update', methods: ['GET','POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        if (!$isAdmin) {
            if ($evenement->getCreateur() !== $user) {
                throw new AccessDeniedException('Vous ne pouvez modifier que vos propres événements.');
            }
            
            if ($this->isGranted('ROLE_PATIENT') && $evenement->getStatut() === 'approuve') {
                $this->addFlash('warning', 'Les événements approuvés ne peuvent plus être modifiés. Contactez un administrateur.');
                if ($isAdmin) {
                    return $this->redirectToRoute('evenements_evenement_view', ['id' => $evenement->getId()]);
                } else {
                    return $this->redirectToRoute('evenements_client_event_view', ['id' => $evenement->getId()]);
                }
            }
        }

        $form = $this->createForm(EvenementType::class, $evenement, [
            'user_role' => $user->getRoles()[0] ?? 'ROLE_USER',
            'is_admin' => $isAdmin,
            'is_edit' => true,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = [];
            
            if (!$evenement->getDateDebut()) {
                $errors[] = 'La date de début est obligatoire.';
            }
            
            if (!$evenement->getDateFin()) {
                $errors[] = 'La date de fin est obligatoire.';
            }
            
            if ($evenement->getDateDebut() && $evenement->getDateFin()) {
                if ($evenement->getDateFin() < $evenement->getDateDebut()) {
                    $errors[] = 'La date de fin doit être postérieure à la date de début.';
                }
                
                $now = new \DateTime();
                $originalDateDebut = $evenement->getDateDebut(); // Note: vous devriez stocker l'ancienne date pour comparaison
                
                if ($evenement->getDateDebut() < $now) {
                    $errors[] = 'Vous ne pouvez pas mettre une date de début dans le passé.';
                }
                
                $diff = $evenement->getDateDebut()->diff($evenement->getDateFin());
                if ($diff->days > 30) {
                    $errors[] = 'La durée de l\'événement ne peut pas dépasser 30 jours.';
                }
            }
            
            if ($evenement->getPlacesMax() !== null) {
                if ($evenement->getPlacesMax() <= 0) {
                    $errors[] = 'Le nombre de places doit être supérieur à zéro.';
                }
                if ($evenement->getPlacesMax() > 10000) {
                    $errors[] = 'Le nombre de places ne peut pas dépasser 10000.';
                }
                
                $inscriptionsCount = $em->getRepository(InscriptionEvenement::class)
                    ->count(['evenement' => $evenement]);
                if ($evenement->getPlacesMax() < $inscriptionsCount) {
                    $errors[] = 'Vous ne pouvez pas réduire le nombre de places en dessous du nombre d\'inscrits actuels (' . $inscriptionsCount . ').';
                }
            }
            
            if ($evenement->getTarif() !== null) {
                if ($evenement->getTarif() < 0) {
                    $errors[] = 'Le tarif ne peut pas être négatif.';
                }
                if ($evenement->getTarif() > 10000) {
                    $errors[] = 'Le tarif ne peut pas dépasser 10000 TND.';
                }
            }
            
            if (strlen($evenement->getTitre()) < 5) {
                $errors[] = 'Le titre doit contenir au moins 5 caractères.';
            }
            
            if (strlen($evenement->getTitre()) > 200) {
                $errors[] = 'Le titre ne peut pas dépasser 200 caractères.';
            }
            
            if ($evenement->getDescription() && strlen($evenement->getDescription()) > 5000) {
                $errors[] = 'La description ne peut pas dépasser 5000 caractères.';
            }
            
            if ($evenement->getLieu() && strlen($evenement->getLieu()) > 500) {
                $errors[] = 'Le lieu ne peut pas dépasser 500 caractères.';
            }
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('evenement/edit.html.twig', [
                    'evenement' => $evenement,
                    'form' => $form->createView(),
                    'is_admin' => $isAdmin,
                ]);
            }

            $evenement->setModifieLe(new \DateTime());
            
            if ($isAdmin && $evenement->getStatut() === 'en_attente_approbation') {
                $evenement->setStatut('planifie');
                $this->addFlash('success', 'Événement approuvé et modifié avec succès.');
            } else {
                $this->addFlash('success', 'Événement modifié avec succès.');
            }
            
            $em->flush();

            // Redirection différente selon le rôle
            if ($isAdmin) {
                return $this->redirectToRoute('evenements_evenement_list');
            } else {
                return $this->redirectToRoute('evenements_client_events');
            }
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form->createView(),
            'is_admin' => $isAdmin,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'evenement_delete', methods: ['POST'])]
    public function delete(Request $request, $id, EntityManagerInterface $em, EvenementRepository $evenementRepository): Response
    {
        $evenement = $evenementRepository->find($id);
        
        if (!$evenement) {
            $this->addFlash('warning', 'Cet événement n\'existe pas ou a déjà été supprimé.');
            return $this->redirectToRoute('evenements_evenement_list');
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        if (!$isAdmin && $evenement->getCreateur() !== $user) {
            throw new AccessDeniedException('Vous ne pouvez supprimer que vos propres événements.');
        }

        $inscriptionsCount = $em->getRepository(InscriptionEvenement::class)
            ->count(['evenement' => $evenement]);
            
        if ($inscriptionsCount > 0 && !$isAdmin) {
            $this->addFlash('danger', 'Impossible de supprimer cet événement car il a déjà des inscriptions. Contactez un administrateur.');
            if ($isAdmin) {
                return $this->redirectToRoute('evenements_evenement_view', ['id' => $evenement->getId()]);
            } else {
                return $this->redirectToRoute('evenements_client_event_view', ['id' => $evenement->getId()]);
            }
        }

        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->request->get('_token'))) {
            $evenementRepository->supprimerEvenement($evenement);
            $this->addFlash('success', 'Événement supprimé avec succès.');
        }

        // Redirection différente selon le rôle
        if ($isAdmin) {
            return $this->redirectToRoute('evenements_evenement_list');
        } else {
            return $this->redirectToRoute('evenements_client_events');
        }
    }

    #[Route('/{id}', name: 'evenement_view', methods: ['GET'])]
    public function view(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('evenements_client_event_view', ['id' => $evenement->getId()]);
        }
        
        $user = $this->getUser();
        
        $nombreParticipants = $this->evenementRepository->getNombreParticipants($evenement);
        $tauxRemplissage = $this->evenementRepository->getTauxRemplissage($evenement);
        $revenusGeneres = $nombreParticipants * ($evenement->getTarif() ?? 0);
        
        $userInscription = null;
        if ($user) {
            try {
                $userInscription = $em->getRepository(InscriptionEvenement::class)
                    ->findOneBy(['evenement' => $evenement, 'utilisateur' => $user]);
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Problème base de données : colonne manquante. Affichage limité.');
                $userInscription = null;
            }
        }

        $canSubscribe = $user ? $this->canUserSubscribe($user, $evenement, $em) : [
            'can_subscribe' => false,
            'message' => 'Vous devez être connecté pour vous inscrire.'
        ];

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isCreator = $user && $evenement->getCreateur() && $evenement->getCreateur() === $user;

        $inscriptionsCount = $em->getRepository(InscriptionEvenement::class)->count(['evenement' => $evenement]);

        $canEdit = $isAdmin || $isCreator;
        $canDelete = ($isAdmin || $isCreator) && ($isAdmin || $inscriptionsCount == 0);
        $canInscribe = $user ? ($isAdmin ? true : ($canSubscribe['can_subscribe'] ?? false)) : false;

        $perms = [
            'can_edit' => (bool) $canEdit,
            'can_delete' => (bool) $canDelete,
            'can_inscribe' => (bool) $canInscribe,
        ];
        
        $participants = $em->getRepository(InscriptionEvenement::class)
            ->findBy(['evenement' => $evenement], ['dateInscription' => 'DESC']);

        return $this->render('evenement/view.html.twig', [
            'evenement' => $evenement,
            'nombreParticipants' => $nombreParticipants,
            'tauxRemplissage' => $tauxRemplissage,
            'revenusGeneres' => $revenusGeneres,
            'user_inscription' => $userInscription,
            'can_subscribe' => $canSubscribe,
            'perms' => $perms,
            'participants' => $participants,
        ]);
    }

    #[Route('/{id}/client-view', name: 'client_event_view', methods: ['GET'])]
    public function clientEventView(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Vérifier l'accès pour les clients (non-admins)
        if (!$isAdmin) {
            // Vérifier si l'utilisateur peut voir cet événement
            if ($user) {
                // Vérifier si c'est le créateur de l'événement
                $isCreator = ($evenement->getCreateur() && $evenement->getCreateur() === $user);
                
                if (!$isCreator) {
                    // Vérifier si l'utilisateur a la méthode getGroupes (uniquement pour les types d'utilisateurs qui ont des groupes)
                    $eventGroups = $evenement->getGroupeCibles();
                    
                    if (!$eventGroups->isEmpty()) {
                        $canView = false;
                        
                        // Vérifier si l'utilisateur a la méthode getGroupes
                        if (method_exists($user, 'getGroupes')) {
                            $userGroups = $user->getGroupes();
                            
                            foreach ($eventGroups as $eventGroup) {
                                if ($userGroups->contains($eventGroup)) {
                                    $canView = true;
                                    break;
                                }
                            }
                        }
                        
                        // Si l'utilisateur n'a pas de groupes ou n'est dans aucun groupe requis
                        if (!$canView) {
                            throw new AccessDeniedException('Vous n\'avez pas accès à cet événement.');
                        }
                    }
                    // Si pas de groupes cibles, l'événement est public - accès autorisé
                }
                // Si c'est le créateur, accès autorisé
            } else {
                // Utilisateur non connecté : peut voir les événements ouverts à tous
                if (!$evenement->getGroupeCibles()->isEmpty()) {
                    $this->addFlash('warning', 'Vous devez être connecté pour accéder à cet événement.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }
        
        // Récupérer les données de l'événement
        $nombreParticipants = $this->evenementRepository->getNombreParticipants($evenement);
        $tauxRemplissage = $this->evenementRepository->getTauxRemplissage($evenement);
        $revenusGeneres = $nombreParticipants * ($evenement->getTarif() ?? 0);
        
        // Vérifier si l'utilisateur est inscrit
        $userInscription = null;
        if ($user) {
            try {
                $userInscription = $em->getRepository(InscriptionEvenement::class)
                    ->findOneBy(['evenement' => $evenement, 'utilisateur' => $user]);
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Problème base de données : colonne manquante. Affichage limité.');
                $userInscription = null;
            }
        }

        // Vérifier si l'utilisateur peut s'inscrire
        $canSubscribe = $user ? $this->canUserSubscribe($user, $evenement, $em) : [
            'can_subscribe' => false,
            'message' => 'Vous devez être connecté pour vous inscrire.'
        ];

        $isCreator = $user && $evenement->getCreateur() && $evenement->getCreateur() === $user;
        $inscriptionsCount = $em->getRepository(InscriptionEvenement::class)->count(['evenement' => $evenement]);

        // Définir les permissions
        $canEdit = $isAdmin || $isCreator;
        $canDelete = ($isAdmin || $isCreator) && ($isAdmin || $inscriptionsCount == 0);
        $canInscribe = $user ? ($isAdmin ? true : ($canSubscribe['can_subscribe'] ?? false)) : false;

        $perms = [
            'can_edit' => (bool) $canEdit,
            'can_delete' => (bool) $canDelete,
            'can_inscribe' => (bool) $canInscribe,
        ];
        
        // Récupérer les participants (limité pour les non-admins)
        $participants = [];
        if ($isAdmin || $isCreator) {
            $participants = $em->getRepository(InscriptionEvenement::class)
                ->findBy(['evenement' => $evenement], ['dateInscription' => 'DESC']);
        }

        // Vérifier si l'utilisateur peut voir les détails complets
        $canViewFullDetails = $isAdmin || $isCreator || !$evenement->getGroupeCibles()->isEmpty();

        return $this->render('evenement/view.html.twig', [
            'evenement' => $evenement,
            'nombreParticipants' => $nombreParticipants,
            'tauxRemplissage' => $tauxRemplissage,
            'revenusGeneres' => $revenusGeneres,
            'user_inscription' => $userInscription,
            'can_subscribe' => $canSubscribe,
            'perms' => $perms,
            'participants' => $participants,
            'is_admin' => $isAdmin,
            'is_creator' => $isCreator,
            'can_view_full_details' => $canViewFullDetails,
        ]);
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(Request $request, EvenementRepository $evenementRepository, EntityManagerInterface $em): Response
    {
        $type = $request->query->get('type');
        $recherche = $request->query->get('recherche');
        
        $user = $this->getUser();
        $evenements = [];
        
        if ($user && !$this->isGranted('ROLE_ADMIN')) {
            // Clients connectés (non-admin)
            $evenements = $evenementRepository->findVisibleEventsForClient($user);
            
            // Appliquer les filtres
            if ($type) {
                $evenements = array_filter($evenements, function($e) use ($type) {
                    return strtolower($e->getType()) === strtolower($type);
                });
            }
            
            if ($recherche) {
                $evenements = array_filter($evenements, function($e) use ($recherche) {
                    return stripos($e->getTitre(), $recherche) !== false || 
                           stripos($e->getDescription(), $recherche) !== false;
                });
            }
        } else {
            // Utilisateurs non connectés ou admins - événements publics
            $evenements = $evenementRepository->findPublicEvents();
        }
        
        // Calculer les statistiques
        $now = new \DateTime();
        $stats = [
            'totalEvents' => count($evenements),
            'upcomingEvents' => count(array_filter($evenements, fn($e) => $e->getDateDebut() > $now)),
            'expertSpeakers' => 25, // À adapter selon votre logique métier
            'happyParticipants' => 1200, // À adapter
        ];
        
        // Permissions pour les actions
        $actions = [];
        foreach ($evenements as $evt) {
            $canEdit = $user && $evt->getCreateur() === $user;
            $canInscrire = false;
            
            if ($user && $user !== $evt->getCreateur()) {
                $subscribeCheck = $this->canUserSubscribe($user, $evt, $em);
                $canInscrire = $subscribeCheck['can_subscribe'];
            }
            
            // Compter les inscriptions (optimisation)
            $evt->inscriptionsCount = $em->getRepository(InscriptionEvenement::class)
                ->count(['evenement' => $evt]);

            $actions[$evt->getId()] = [
                'can_edit' => (bool) $canEdit,
                'can_inscribe' => (bool) $canInscrire,
            ];
        }
        
        $hasClientRole = false;
        if ($user) {
            $allowedClientRoles = ['ROLE_MEDECIN', 'ROLE_RESPONSABLE_LABO', 'ROLE_RESPONSABLE_PARA', 'ROLE_PATIENT'];
            foreach ($allowedClientRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasClientRole = true;
                    break;
                }
            }
        }
        
        return $this->render('home/index.html.twig', [
            'evenements' => array_slice($evenements, 0, 6), 
            'stats' => $stats,
            'actions' => $actions,
            'type' => $type,
            'recherche' => $recherche,
            'has_permission_to_create' => $hasClientRole,
        ]);
    }

    #[Route('/{id}/inscrire', name: 'evenement_inscrire', methods: ['POST'])]
    public function inscrire(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté pour vous inscrire.');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si l'utilisateur peut s'inscrire
        $canSubscribe = $this->canUserSubscribe($user, $evenement, $em);
        
        if (!$canSubscribe['can_subscribe']) {
            $this->addFlash('warning', $canSubscribe['message']);
            $route = $this->isGranted('ROLE_ADMIN') ? 'evenements_evenement_view' : 'evenements_client_event_view';
            return $this->redirectToRoute($route, ['id' => $evenement->getId()]);
        }

        // Vérifier si déjà inscrit
        $existing = $em->getRepository(InscriptionEvenement::class)
            ->findOneBy(['evenement' => $evenement, 'utilisateur' => $user]);

        if ($existing) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cet événement.');
            $route = $this->isGranted('ROLE_ADMIN') ? 'evenements_evenement_view' : 'evenements_client_event_view';
            return $this->redirectToRoute($route, ['id' => $evenement->getId()]);
        }

        // Vérifier les places disponibles
        if ($evenement->getPlacesMax() !== null) {
            $inscriptionsCount = $em->getRepository(InscriptionEvenement::class)
                ->count(['evenement' => $evenement]);
                
            if ($inscriptionsCount >= $evenement->getPlacesMax()) {
                $this->addFlash('danger', 'Désolé, cet événement est complet.');
                $route = $this->isGranted('ROLE_ADMIN') ? 'evenements_evenement_view' : 'evenements_client_event_view';
                return $this->redirectToRoute($route, ['id' => $evenement->getId()]);
            }
        }

        // Créer l'inscription
        $inscription = new InscriptionEvenement();
        $inscription->setEvenement($evenement);
        $inscription->setUtilisateur($user);
        $inscription->setDateInscription(new \DateTime());
        $inscription->setStatut('confirme');
        $inscription->setPresent(false);
        $inscription->setCreeLe(new \DateTime());

        $em->persist($inscription);
        $em->flush();

        $this->addFlash('success', 'Inscription réussie !');
        $route = $this->isGranted('ROLE_ADMIN') ? 'evenements_evenement_view' : 'evenements_client_event_view';
        return $this->redirectToRoute($route, ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/desinscrire', name: 'evenement_desinscrire', methods: ['POST'])]
    public function desinscrire(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $inscription = $em->getRepository(InscriptionEvenement::class)
            ->findOneBy(['evenement' => $evenement, 'utilisateur' => $user]);

        if ($inscription) {
            $em->remove($inscription);
            $em->flush();
            $this->addFlash('success', 'Vous avez été désinscrit de cet événement.');
        }

        $route = $this->isGranted('ROLE_ADMIN') ? 'evenements_evenement_view' : 'evenements_client_event_view';
        return $this->redirectToRoute($route, ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/approuver', name: 'evenement_approuver', methods: ['POST'])]
    public function approuver(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($evenement->getStatut() === 'en_attente_approbation') {
            $evenement->setStatut('planifie');
            $em->flush();
            
            $this->addFlash('success', 'Événement approuvé avec succès.');
        }

        return $this->redirectToRoute('evenements_evenement_view', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/participants', name: 'evenement_participants', methods: ['GET'])]
    public function participants(Evenement $evenement, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $participants = $em->getRepository(InscriptionEvenement::class)
            ->findBy(['evenement' => $evenement]);

        return $this->render('evenement/participants.html.twig', [
            'evenement' => $evenement,
            'participants' => $participants,
        ]);
    }

    #[Route('/calendrier', name: 'evenement_calendar', methods: ['GET'])]
    public function calendar(): Response
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        if ($isAdmin) {
            $evenements = $this->evenementRepository->findAll();
        } else {
            $evenements = $this->evenementRepository->findPublicEvents();
        }

        return $this->render('evenement/calendar.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/client/events', name: 'client_events', methods: ['GET'])]
    public function clientEvents(Request $request, EvenementRepository $evenementRepository, EntityManagerInterface $em): Response
    {
        $allowedClientRoles = ['ROLE_MEDECIN', 'ROLE_RESPONSABLE_LABO', 'ROLE_RESPONSABLE_PARA', 'ROLE_PATIENT'];
        $hasClientRole = false;
        foreach ($allowedClientRoles as $role) {
            if ($this->isGranted($role)) { 
                $hasClientRole = true; 
                break; 
            }
        }
        
        if ($this->isGranted('ROLE_ADMIN')) { 
            return $this->redirectToRoute('evenements_evenement_list'); 
        }
        if (!$hasClientRole && !$this->getUser()) { 
            return $this->redirectToRoute('app_login'); 
        }

        $user = $this->getUser();
        $type = $request->query->get('type');
        $recherche = $request->query->get('recherche');
        
        $evenementsApprouves = [];
        $demandesEnAttente = [];
        
        if ($user) {
            // 1. Get Visible Events
            $rawEvents = $evenementRepository->findVisibleEventsForClient($user);

            // Filtrer les événements en attente
            $evenementsApprouves = array_filter($rawEvents, function($e) {
                return !in_array($e->getStatut(), ['en_attente_approbation', 'annule', 'refuse']);
            });
            
            // 2. Get Pending Events Separately
            $demandesEnAttente = $evenementRepository->findBy([
                'createur' => $user,
                'statut' => 'en_attente_approbation'
            ], ['creeLe' => 'DESC']);
            
        } else {
            // Not connected logic
            $evenementsApprouves = $evenementRepository->findBy(['statut' => ['planifie', 'confirme', 'en_cours']], ['dateDebut' => 'ASC']);
            $evenementsApprouves = array_filter($evenementsApprouves, function($event) {
                return $event->getGroupeCibles()->isEmpty();
            });
        }
        
        // Apply filters to Approved events only
        if ($type) {
            $evenementsApprouves = array_filter($evenementsApprouves, fn($e) => strtolower($e->getType()) === strtolower($type));
        }
        if ($recherche) {
            $recherche = strtolower($recherche);
            $evenementsApprouves = array_filter($evenementsApprouves, fn($e) => 
                stripos(strtolower($e->getTitre()), $recherche) !== false || 
                stripos(strtolower($e->getDescription() ?? ''), $recherche) !== false
            );
        }

        // Calculate stats
        $now = new \DateTime();
        $stats = [
            'totalEvents' => count($evenementsApprouves),
            'upcomingEvents' => count(array_filter($evenementsApprouves, fn($e) => $e->getDateDebut() > $now)),
            'expertSpeakers' => 25,
            'happyParticipants' => 1200,
        ];
        
        // Actions logic
        $actions = [];
        foreach ($evenementsApprouves as $evt) {
            $canInscrire = false;
            if ($user && $user !== $evt->getCreateur()) {
                $subscribeCheck = $this->canUserSubscribe($user, $evt, $em);
                $canInscrire = $subscribeCheck['can_subscribe'];
            }
            $actions[$evt->getId()] = ['can_edit' => false, 'can_inscribe' => (bool) $canInscrire];
        }
        
        // Also add actions for Pending events (so they can be edited/deleted if your template allows)
        foreach ($demandesEnAttente as $evt) {
            $actions[$evt->getId()] = ['can_edit' => true, 'can_inscribe' => false];
        }

        return $this->render('evenement/client.html.twig', [
            'evenements' => $evenementsApprouves, // Contains ONLY approved
            'demandes_en_attente' => $demandesEnAttente, // Contains ONLY pending
            'actions' => $actions,
            'stats' => $stats,
            'type' => $type,
            'recherche' => $recherche,
            'has_permission_to_create' => $hasClientRole,
            'user' => $user,
            'show_pending_section' => !empty($demandesEnAttente),
        ]);
    }

    private function canUserSubscribe(Utilisateur $user, Evenement $evenement, EntityManagerInterface $em): array
    {
        if (!in_array($evenement->getStatut(), ['planifie', 'approuve'])) {
            return [
                'can_subscribe' => false,
                'message' => 'Cet événement n\'est pas ouvert aux inscriptions.'
            ];
        }

        if ($evenement->getCreateur() === $user) {
            return [
                'can_subscribe' => false,
                'message' => 'Vous ne pouvez pas vous inscrire à votre propre événement.'
            ];
        }

        $eventGroups = $evenement->getGroupeCibles();
        
        // Check if user is an admin (they can subscribe to everything)
        if ($this->isGranted('ROLE_ADMIN')) {
            return [
                'can_subscribe' => true,
                'message' => ''
            ];
        }
        
        // For non-admin users, check if they have the getGroupes method
        $userGroups = [];
        if (method_exists($user, 'getGroupes')) {
            $userGroups = $user->getGroupes();
        } else {
            // User type doesn't have groups (like Administrateur)
            // But we already checked for admin role, so this shouldn't happen
            // If it does, they can't subscribe to group-restricted events
            $userGroups = new \Doctrine\Common\Collections\ArrayCollection();
        }
        
        if (!$eventGroups->isEmpty()) {
            $hasMatchingGroup = false;
            foreach ($eventGroups as $eventGroup) {
                if ($userGroups->contains($eventGroup)) {
                    $hasMatchingGroup = true;
                    break;
                }
            }
            
            if (!$hasMatchingGroup) {
                return [
                    'can_subscribe' => false,
                    'message' => 'Cet événement n\'est pas destiné à votre profil.'
                ];
            }
        }

        $now = new \DateTime();
        if ($evenement->getDateDebut() <= $now) {
            return [
                'can_subscribe' => false,
                'message' => 'Les inscriptions sont closes pour cet événement.'
            ];
        }

        return [
            'can_subscribe' => true,
            'message' => ''
        ];
    }

    #[Route('/admin/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(
        EntityManagerInterface $em,
        InscriptionEvenementRepository $inscriptionRepo,
        GroupeCibleRepository $groupeRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        $now = new \DateTime();
        $lastWeek = clone $now;
        $lastWeek->modify('-7 days');
        $lastMonth = clone $now;
        $lastMonth->modify('-30 days');

        // Statistiques de base
        $totalEvenements = $this->evenementRepository->count([]);
        
        // Événements à venir (dateDebut > maintenant)
        $evenementsAVenir = $this->evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.dateDebut > :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        // Événements créés cette semaine
        $evenementsSemaine = $this->evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.creeLe >= :lastWeek')
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult();

        // Événements créés ce mois
        $evenementsMois = $this->evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.creeLe >= :lastMonth')
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Total des inscriptions
        $totalInscriptions = $inscriptionRepo->count([]);
        
        // Inscriptions cette semaine
        $inscriptionsSemaine = $inscriptionRepo->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.creeLe >= :lastWeek')
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult();

        // Inscriptions ce mois
        $inscriptionsMois = $inscriptionRepo->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.creeLe >= :lastMonth')
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();

        // Événements récents (6 derniers)
        $evenementsRecents = $this->evenementRepository->createQueryBuilder('e')
            ->orderBy('e.creeLe', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        // Événements prochains (5 prochains)
        $evenementsProchains = $this->evenementRepository->createQueryBuilder('e')
            ->where('e.dateDebut > :now')
            ->setParameter('now', $now)
            ->orderBy('e.dateDebut', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Répartition par type
        $evenementsParType = $this->evenementRepository->createQueryBuilder('e')
            ->select('e.type, COUNT(e.id) as count')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();

        // Répartition par statut
        $evenementsParStatut = $this->evenementRepository->createQueryBuilder('e')
            ->select('e.statut, COUNT(e.id) as count')
            ->groupBy('e.statut')
            ->getQuery()
            ->getResult();

        // Répartition par mode (en_ligne, presentiel, hybride)
        $evenementsParMode = $this->evenementRepository->createQueryBuilder('e')
            ->select('e.mode, COUNT(e.id) as count')
            ->where('e.mode IS NOT NULL')
            ->groupBy('e.mode')
            ->getQuery()
            ->getResult();

        // Demandes en attente d'approbation
        $demandesEnAttente = $this->evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.statutDemande = :statut')
            ->setParameter('statut', 'en_attente_approbation')
            ->getQuery()
            ->getSingleScalarResult();

        // Top 5 événements avec le plus d'inscriptions
        $topEvenements = $this->evenementRepository->createQueryBuilder('e')
            ->select('e.id, e.titre, COUNT(i.id) as inscriptions')
            ->leftJoin('e.inscriptions', 'i')
            ->groupBy('e.id')
            ->orderBy('inscriptions', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Top 5 utilisateurs avec le plus d'inscriptions
        $topUtilisateurs = $inscriptionRepo->createQueryBuilder('i')
            ->select('u.id, u.prenom, u.nom, COUNT(i.id) as inscriptions')
            ->join('i.utilisateur', 'u')
            ->groupBy('u.id')
            ->orderBy('inscriptions', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Nombre total de groupes cibles
        $totalGroupesCibles = $groupeRepo->count([]);

        // Taux de remplissage moyen
        $tauxRemplissageMoyen = 0;
        $evenementsAvecPlaces = $this->evenementRepository->createQueryBuilder('e')
            ->where('e.placesMax IS NOT NULL')
            ->andWhere('e.placesMax > 0')
            ->getQuery()
            ->getResult();
        
        $totalTaux = 0;
        $countTaux = 0;
        foreach ($evenementsAvecPlaces as $evt) {
            $inscriptions = $inscriptionRepo->count(['evenement' => $evt]);
            if ($evt->getPlacesMax() > 0) {
                $taux = ($inscriptions / $evt->getPlacesMax()) * 100;
                $totalTaux += $taux;
                $countTaux++;
            }
        }
        $tauxRemplissageMoyen = $countTaux > 0 ? round($totalTaux / $countTaux, 1) : 0;

        // Événements gratuits vs payants
        $evenementsGratuits = $this->evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.tarif IS NULL OR e.tarif = 0')
            ->getQuery()
            ->getSingleScalarResult();

        $evenementsPayants = $this->evenementRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.tarif IS NOT NULL')
            ->andWhere('e.tarif > 0')
            ->getQuery()
            ->getSingleScalarResult();

        // Revenus totaux estimés
        $revenusTotaux = $inscriptionRepo->createQueryBuilder('i')
            ->select('SUM(e.tarif)')
            ->join('i.evenement', 'e')
            ->where('e.tarif IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Statistiques par mois pour le graphique d'évolution
        $evolutionMensuelle = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = clone $now;
            $month->modify("-$i months");
            $monthStart = clone $month;
            $monthStart->modify('first day of this month')->setTime(0, 0, 0);
            $monthEnd = clone $month;
            $monthEnd->modify('last day of this month')->setTime(23, 59, 59);

            $count = $this->evenementRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.creeLe BETWEEN :start AND :end')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $evolutionMensuelle[] = [
                'mois' => $month->format('m/Y'),
                'count' => $count
            ];
        }

        return $this->render('evenement/dashboard.html.twig', [
            'user' => $user,
            
            // Statistiques générales
            'totalEvenements' => $totalEvenements,
            'evenementsAVenir' => $evenementsAVenir,
            'evenementsSemaine' => $evenementsSemaine,
            'evenementsMois' => $evenementsMois,
            'totalInscriptions' => $totalInscriptions,
            'inscriptionsSemaine' => $inscriptionsSemaine,
            'inscriptionsMois' => $inscriptionsMois,
            'demandesEnAttente' => $demandesEnAttente,
            
            // Listes
            'evenementsRecents' => $evenementsRecents,
            'evenementsProchains' => $evenementsProchains,
            
            // Répartitions
            'evenementsParType' => $evenementsParType,
            'evenementsParStatut' => $evenementsParStatut,
            'evenementsParMode' => $evenementsParMode,
            
            // Tops
            'topEvenements' => $topEvenements,
            'topUtilisateurs' => $topUtilisateurs,
            
            // Statistiques avancées
            'totalGroupesCibles' => $totalGroupesCibles,
            'tauxRemplissageMoyen' => $tauxRemplissageMoyen,
            'evenementsGratuits' => $evenementsGratuits,
            'evenementsPayants' => $evenementsPayants,
            'revenusTotaux' => $revenusTotaux,
            'evolutionMensuelle' => $evolutionMensuelle,
            
            // Pour les graphiques
            'stats' => [
                'total' => $totalEvenements,
                'a_venir' => $evenementsAVenir,
                'semaine' => $evenementsSemaine,
                'mois' => $evenementsMois,
                'inscriptions_total' => $totalInscriptions,
                'inscriptions_semaine' => $inscriptionsSemaine,
                'inscriptions_mois' => $inscriptionsMois,
                'gratuits' => $evenementsGratuits,
                'payants' => $evenementsPayants,
                'revenus' => $revenusTotaux,
                'groupes' => $totalGroupesCibles,
                'taux_remplissage' => $tauxRemplissageMoyen,
                'demandes_attente' => $demandesEnAttente,
            ],
        ]);
    }

    #[Route('/client/demande-evenement', name: 'client_demande_evenement', methods: ['GET', 'POST'])]
    public function demandeEvenement(Request $request, EntityManagerInterface $em): Response 
    {
        $user = $this->getUser();
        
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        $evenement = new Evenement();
        
        $evenement->setStatut('en_attente_approbation'); 
        $evenement->setStatutDemande('en_attente_approbation');
        $evenement->setCreeLe(new \DateTime());
        $evenement->setCreateur($user);

        $form = $this->createForm(EvenementType::class, $evenement, [
            'user_role' => $user->getRoles()[0] ?? 'ROLE_USER',
            'is_admin' => false,
            'is_demande' => true, 
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $errors = $this->validateEvenement($evenement);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                return $this->render('evenement/client_ajout_demande.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Votre demande a été envoyée.');
            return $this->redirectToRoute('evenements_client_events');
        }
        
        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('evenement/client_ajout_demande.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/demandes-evenements', name: 'admin_demandes_evenements', methods: ['GET'])]
    public function demandesEvenements(
        Request $request,
        EvenementRepository $evenementRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $recherche = $request->query->get('recherche');
        $type = $request->query->get('type');

        $qb = $evenementRepository->createQueryBuilder('e')
            ->where('e.statutDemande = :statut')
            ->setParameter('statut', 'en_attente_approbation')
            ->orderBy('e.creeLe', 'DESC');

        if ($recherche) {
            $qb->andWhere('e.titre LIKE :recherche OR e.description LIKE :recherche')
               ->setParameter('recherche', '%' . $recherche . '%');
        }

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        $demandes = $qb->getQuery()->getResult();

        return $this->render('evenement/admin_demandes_evenements.html.twig', [
            'demandes' => $demandes,
            'recherche' => $recherche,
            'type' => $type,
        ]);
    }

    #[Route('/admin/evenement/{id}/approuver', name: 'admin_evenement_approuver', methods: ['POST'])]
    public function approuverDemande(
        Request $request,
        Evenement $evenement,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$evenement) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        $evenement->setStatutDemande('approuve');
        $evenement->setStatut('planifie');
        $evenement->setModifieLe(new \DateTime());

        $em->flush();

        $this->addFlash('success', 'Événement approuvé.');

        return $this->redirectToRoute('evenements_admin_demandes_evenements');
    }

    #[Route('/admin/evenement/{id}/refuser', name: 'evenement_refuser', methods: ['POST'])]
    public function refuserDemande(
        Request $request,
        Evenement $evenement,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$evenement) {
            throw $this->createNotFoundException('Événement non trouvé');
        }

        $evenement->setStatutDemande('refuse');
        $evenement->setStatut('annule');
        $evenement->setModifieLe(new \DateTime());

        $em->flush();

        $this->addFlash('warning', 'Événement refusé.');

        return $this->redirectToRoute('evenements_admin_demandes_evenements');
    }

    private function validateEvenement(Evenement $evenement): array
    {
        $errors = [];

        if (!$evenement->getDateDebut()) {
            $errors[] = 'La date de début est obligatoire.';
        }

        if (!$evenement->getDateFin()) {
            $errors[] = 'La date de fin est obligatoire.';
        }

        if ($evenement->getDateDebut() && $evenement->getDateFin()) {
            if ($evenement->getDateFin() < $evenement->getDateDebut()) {
                $errors[] = 'La date de fin doit être après la date de début.';
            }

            $minDate = new \DateTime();
            $minDate->modify('+2 days');

            if ($evenement->getDateDebut() < $minDate) {
                $errors[] = 'La date doit être au moins 48h à l\'avance.';
            }

            $diff = $evenement->getDateDebut()->diff($evenement->getDateFin());

            if ($diff->days > 30) {
                $errors[] = 'Durée max : 30 jours.';
            }
        }

        if ($evenement->getPlacesMax() !== null) {
            if ($evenement->getPlacesMax() <= 0) {
                $errors[] = 'Places > 0 obligatoire.';
            }

            if ($evenement->getPlacesMax() > 10000) {
                $errors[] = 'Places max : 10000.';
            }
        }

        return $errors;
    }

    #[Route('/client/mes-inscriptions', name: 'client_mes_inscriptions', methods: ['GET'])]
    public function mesInscriptions(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $inscriptions = $em->getRepository(InscriptionEvenement::class)
            ->findBy(
                ['utilisateur' => $user],
                ['dateInscription' => 'DESC']
            );

        return $this->render('evenement/mes_inscriptions.html.twig', [
            'inscriptions' => $inscriptions,
        ]);
    }

    #[Route('/client/{id}/desinscrire', name: 'client_desinscrire', methods: ['POST'])]
    public function clientDesinscrire(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérification du token CSRF
        if (!$this->isCsrfTokenValid('desinscrire' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('evenements_client_mes_inscriptions');
        }

        // Vérification du délai de 24h
        $now = new \DateTime();
        $interval = $now->diff($evenement->getDateDebut());
        $heuresRestantes = ($interval->days * 24) + $interval->h;
        
        if ($heuresRestantes < 24 && $interval->invert == 0) {
            $this->addFlash('error', 'Désolé, vous ne pouvez plus vous désinscrire moins de 24h avant l\'événement.');
            return $this->redirectToRoute('evenements_client_mes_inscriptions');
        }

        $inscription = $em->getRepository(InscriptionEvenement::class)
            ->findOneBy(['evenement' => $evenement, 'utilisateur' => $user]);

        if ($inscription) {
            $em->remove($inscription);
            $em->flush();
            $this->addFlash('success', 'Votre désinscription a été effectuée avec succès.');
        }

        return $this->redirectToRoute('evenements_client_mes_inscriptions');
    }

    #[Route('/client/tous-les-evenements', name: 'client_all_events', methods: ['GET'])]
    public function clientAllEvents(Request $request, EvenementRepository $evenementRepository, EntityManagerInterface $em): Response
    {
        // Vérifier que l'utilisateur a un rôle client valide
        $allowedClientRoles = ['ROLE_MEDECIN', 'ROLE_RESPONSABLE_LABO', 'ROLE_RESPONSABLE_PARA', 'ROLE_PATIENT'];
        $hasClientRole = false;
        
        foreach ($allowedClientRoles as $role) {
            if ($this->isGranted($role)) {
                $hasClientRole = true;
                break;
            }
        }
        
        // Si admin, rediriger vers la page admin
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('evenements_evenement_list');
        }
        
        // Si pas de rôle client valide et pas connecté, rediriger vers login
        if (!$hasClientRole && !$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        $type = $request->query->get('type');
        $recherche = $request->query->get('recherche');
        $page = $request->query->getInt('page', 1);
        $limit = 9; // Nombre d'événements par page
        
        // Récupérer TOUS les événements pertinents pour le client
        $evenements = [];
        
        if ($user) {
            // 1. Événements publics ou pour ses groupes
            $visibleEvents = $evenementRepository->findVisibleEventsForClient($user);
            
            // 2. Ses propres événements en attente d'approbation
            $demandesEnAttente = $evenementRepository->findBy([
                'createur' => $user,
                'statutDemande' => 'en_attente_approbation'
            ]);
            
            // 3. Ses propres événements approuvés
            $mesEvenementsApprouves = $evenementRepository->findBy([
                'createur' => $user,
                'statut' => ['planifie', 'confirme', 'en_cours']
            ]);
            
            // Fusionner tous les événements
            $evenements = array_merge($visibleEvents, $demandesEnAttente, $mesEvenementsApprouves);
            
            // Supprimer les doublons
            $evenements = array_reduce($evenements, function($carry, $item) {
                $carry[$item->getId()] = $item;
                return $carry;
            }, []);
            $evenements = array_values($evenements);
            
        } else {
            // Utilisateur non connecté : seulement les événements publics
            $evenements = $evenementRepository->findBy([
                'statut' => ['planifie', 'confirme']
            ], ['dateDebut' => 'ASC']);
            
            // Filtrer ceux qui ont des groupes cibles
            $evenements = array_filter($evenements, function($event) {
                return $event->getGroupeCibles()->isEmpty();
            });
        }
        
        // Appliquer les filtres
        if ($type) {
            $evenements = array_filter($evenements, function($evenement) use ($type) {
                return strtolower($evenement->getType()) === strtolower($type);
            });
        }
        
        if ($recherche) {
            $recherche = strtolower($recherche);
            $evenements = array_filter($evenements, function($evenement) use ($recherche) {
                return stripos(strtolower($evenement->getTitre()), $recherche) !== false || 
                       stripos(strtolower($evenement->getDescription() ?? ''), $recherche) !== false;
            });
        }
        
        // Trier par date de début
        usort($evenements, function($a, $b) {
            return $a->getDateDebut() <=> $b->getDateDebut();
        });
        
        // Pagination
        $total = count($evenements);
        $offset = ($page - 1) * $limit;
        $evenementsPagines = array_slice($evenements, $offset, $limit);
        $totalPages = ceil($total / $limit);
        
        // Compter les inscriptions pour chaque événement
        foreach ($evenementsPagines as $evt) {
            try {
                $evt->inscriptionsCount = $em->getRepository(InscriptionEvenement::class)
                    ->count(['evenement' => $evt]);
            } catch (\Exception $e) {
                $evt->inscriptionsCount = 0;
            }
        }

        // Statistiques
        $now = new \DateTime();
        $stats = [
            'total' => $total,
            'a_venir' => count(array_filter($evenements, function($e) use ($now) {
                return $e->getDateDebut() > $now;
            })),
            'gratuits' => count(array_filter($evenements, function($e) {
                return $e->getTarif() == 0 || $e->getTarif() === null;
            })),
            'payants' => count(array_filter($evenements, function($e) {
                return $e->getTarif() > 0;
            }))
        ];

        return $this->render('evenement/clients_evenements.html.twig', [
            'evenements' => $evenementsPagines,
            'stats' => $stats,
            'type' => $type,
            'recherche' => $recherche,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'has_permission_to_create' => $hasClientRole,
        ]);
    }

    #[Route('/client/tous-les-evenements', name: 'client_all_evenements', methods: ['GET'])]
    public function clientEvenements(
        Request $request, 
        EvenementRepository $evenementRepository, 
        EntityManagerInterface $em
    ): Response {
        // Vérifier que l'utilisateur a un rôle client valide (non admin)
        $allowedClientRoles = ['ROLE_MEDECIN', 'ROLE_RESPONSABLE_LABO', 'ROLE_RESPONSABLE_PARA', 'ROLE_PATIENT'];
        $hasClientRole = false;
        
        $user = $this->getUser();
        
        // Vérifier les rôles si l'utilisateur est connecté
        if ($user) {
            foreach ($allowedClientRoles as $role) {
                if ($this->isGranted($role)) {
                    $hasClientRole = true;
                    break;
                }
            }
        }
        
        // Si admin, rediriger vers la page admin
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('evenements_evenement_list');
        }
        
        // Récupérer les paramètres de filtrage
        $type = $request->query->get('type');
        $recherche = $request->query->get('recherche');
        $tri = $request->query->get('tri', 'date_asc');
        $page = $request->query->getInt('page', 1);
        $limit = 9;
        
        // Récupérer tous les événements pertinents pour le client
        $evenements = [];
        
        if ($user) {
            $visibleEvents = $evenementRepository->findVisibleEventsForClient($user);
            $demandesEnAttente = $evenementRepository->findPendingEventsForUser($user);
            $mesEvenementsApprouves = $evenementRepository->findBy([
                'createur' => $user,
                'statut' => ['planifie', 'confirme', 'en_cours']
            ]);
            
            $evenements = array_merge($visibleEvents, $demandesEnAttente, $mesEvenementsApprouves);
        } else {
            $evenements = $evenementRepository->findBy([
                'statut' => ['planifie', 'confirme']
            ], ['dateDebut' => 'ASC']);
            
            $evenements = array_filter($evenements, function($event) {
                return $event->getGroupeCibles()->isEmpty();
            });
        }
        
        // Supprimer les doublons
        $evenements = array_reduce($evenements, function($carry, $item) {
            $carry[$item->getId()] = $item;
            return $carry;
        }, []);
        $evenements = array_values($evenements);
        
        // Appliquer les filtres
        if ($type) {
            $evenements = array_filter($evenements, function($evenement) use ($type) {
                return strtolower($evenement->getType()) === strtolower($type);
            });
        }
        
        if ($recherche) {
            $recherche = strtolower($recherche);
            $evenements = array_filter($evenements, function($evenement) use ($recherche) {
                return stripos(strtolower($evenement->getTitre()), $recherche) !== false || 
                       stripos(strtolower($evenement->getDescription() ?? ''), $recherche) !== false;
            });
        }
        
        // Trier les événements
        usort($evenements, function($a, $b) use ($tri) {
            switch ($tri) {
                case 'date_desc':
                    return $b->getDateDebut() <=> $a->getDateDebut();
                case 'prix_asc':
                    $prixA = $a->getTarif() ?? 0;
                    $prixB = $b->getTarif() ?? 0;
                    return $prixA <=> $prixB;
                case 'prix_desc':
                    $prixA = $a->getTarif() ?? 0;
                    $prixB = $b->getTarif() ?? 0;
                    return $prixB <=> $prixA;
                case 'date_asc':
                default:
                    return $a->getDateDebut() <=> $b->getDateDebut();
            }
        });
        
        // Récupérer les inscriptions de l'utilisateur
        $userInscriptions = [];
        if ($user) {
            $inscriptions = $em->getRepository(InscriptionEvenement::class)
                ->findBy(['utilisateur' => $user]);
            foreach ($inscriptions as $inscription) {
                $userInscriptions[$inscription->getEvenement()->getId()] = true;
            }
        }
        
        // Ajouter les compteurs et statuts d'inscription
        foreach ($evenements as $evt) {
            try {
                $evt->inscriptionsCount = $em->getRepository(InscriptionEvenement::class)
                    ->count(['evenement' => $evt]);
                // Ajouter le flag userInscrit
                $evt->userInscrit = isset($userInscriptions[$evt->getId()]);
            } catch (\Exception $e) {
                $evt->inscriptionsCount = 0;
                $evt->userInscrit = false;
            }
        }
        
        // Pagination
        $total = count($evenements);
        $offset = ($page - 1) * $limit;
        $evenementsPagines = array_slice($evenements, $offset, $limit);
        $totalPages = ceil($total / $limit);
        
        // Statistiques
        $now = new \DateTime();
        $stats = [
            'total' => $total,
            'a_venir' => count(array_filter($evenements, function($e) use ($now) {
                return $e->getDateDebut() > $now;
            })),
            'gratuits' => count(array_filter($evenements, function($e) {
                return $e->getTarif() == 0 || $e->getTarif() === null;
            })),
            'payants' => count(array_filter($evenements, function($e) {
                return $e->getTarif() > 0;
            }))
        ];

        return $this->render('evenement/clients_evenements.html.twig', [
            'evenements' => $evenementsPagines,
            'stats' => $stats,
            'type' => $type,
            'recherche' => $recherche,
            'tri' => $tri,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'has_permission_to_create' => $hasClientRole,
        ]);
    }
}