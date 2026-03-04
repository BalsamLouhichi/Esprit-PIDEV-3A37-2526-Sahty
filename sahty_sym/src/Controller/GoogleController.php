<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Patient;
use App\Entity\Medecin;
use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class GoogleController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        EntityManagerInterface $entityManager, 
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher
    )
    {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Redirects to Google for authentication.
     */
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        // Keep the same host between /connect/google and /connect/google/check
        // so OAuth state stored in session stays valid.
        if ($request->hasSession() && !$request->getSession()->isStarted()) {
            $request->getSession()->start();
        }

        $redirectOptions = [];
        $configuredRedirect = trim((string) ($_ENV['OAUTH_GOOGLE_REDIRECT_URI'] ?? getenv('OAUTH_GOOGLE_REDIRECT_URI') ?? ''));
        if ($configuredRedirect !== '') {
            $redirectOptions['redirect_uri'] = $configuredRedirect;
        }

        return $clientRegistry->getClient('google')->redirect([
            'profile', 'email'
        ], $redirectOptions);
    }

    /**
     * After going to Google, you're redirected back here.
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(Request $request, ClientRegistry $clientRegistry): Response
    {
        try {
            /** @var OAuth2Client $client */
            $client = $clientRegistry->getClient('google');
            
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUser();

            // Chercher l'utilisateur existant par email
            $utilisateur = $this->entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $googleUser->getEmail()]);

            // Si l'utilisateur existe déjà, le connecter via l'endpoint intermédiaire
            if ($utilisateur) {
                $session = $request->getSession();
                $session->set('_oauth_user_id', $utilisateur->getId());
                $session->set('_oauth_authenticated', true);
                $session->save(); // IMPORTANT: Sauvegarder avant redirection
                return $this->redirectToRoute('oauth_authenticate');
            }

            // Nouvel utilisateur Google - stocker les données en session et rediriger vers sélection de rôle
            $session = $request->getSession();
            $session->set('google_user_data', [
                'email' => $googleUser->getEmail(),
                'firstName' => $googleUser->getFirstName() ?? 'User',
                'lastName' => $googleUser->getLastName() ?? 'Google',
            ]);

            return $this->redirectToRoute('google_select_role');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion Google: ' . $e->getMessage());
            return $this->redirectToRoute('app_login');
        }
    }

    /**
     * Afficher la page de sélection du rôle
     */
    #[Route('/google/select-role', name: 'google_select_role')]
    public function selectRole(Request $request): Response
    {
        $session = $request->getSession();
        $googleUserData = $session->get('google_user_data');

        // Vérifier que l'utilisateur vient de Google
        if (!$googleUserData) {
            $this->addFlash('error', 'Données Google non trouvées');
            return $this->redirectToRoute('app_login');
        }

        // Liste des rôles disponibles
        $roles = [
            ['value' => Utilisateur::ROLE_SIMPLE_PATIENT, 'label' => 'Patient', 'icon' => 'fa-user-injured', 'description' => 'Accédez à vos dossiers médicaux'],
            ['value' => Utilisateur::ROLE_SIMPLE_MEDECIN, 'label' => 'Médecin', 'icon' => 'fa-user-md', 'description' => 'Gèrez vos patients'],
            ['value' => Utilisateur::ROLE_SIMPLE_RESPONSABLE_LABO, 'label' => 'Laboratoire', 'icon' => 'fa-flask', 'description' => 'Gèrez vos analyses'],
            ['value' => Utilisateur::ROLE_SIMPLE_RESPONSABLE_PARA, 'label' => 'Parapharmacie', 'icon' => 'fa-pills', 'description' => 'Gèrez vos produits'],
        ];

        return $this->render('security/google_select_role.html.twig', [
            'roles' => $roles,
            'userData' => $googleUserData,
        ]);
    }

    /**
     * Traiter la sélection du rôle et créer l'utilisateur
     */
    #[Route('/google/confirm-role', name: 'google_confirm_role', methods: ['POST'])]
    public function confirmRole(Request $request): Response
    {
        $session = $request->getSession();
        $googleUserData = $session->get('google_user_data');

        // Vérifier que les données existent
        if (!$googleUserData) {
            $this->addFlash('error', 'Données Google expirées');
            return $this->redirectToRoute('app_login');
        }

        $selectedRole = $request->request->get('role');

        // Valider le rôle sélectionné
        $validRoles = [
            Utilisateur::ROLE_SIMPLE_PATIENT,
            Utilisateur::ROLE_SIMPLE_MEDECIN,
            Utilisateur::ROLE_SIMPLE_RESPONSABLE_LABO,
            Utilisateur::ROLE_SIMPLE_RESPONSABLE_PARA,
        ];

        if (!in_array($selectedRole, $validRoles)) {
            $this->addFlash('error', 'Rôle invalide');
            return $this->redirectToRoute('app_login');
        }

        try {
            // Créer le nouvel utilisateur avec la BONNE CLASSE enfante selon le rôle
            // (Important pour Single Table Inheritance - Doctrine a besoin du discriminator)
            $utilisateur = $this->createUtilisateurByRole($selectedRole);
            
            $utilisateur->setEmail($googleUserData['email']);
            $utilisateur->setNom($googleUserData['lastName']);
            $utilisateur->setPrenom($googleUserData['firstName']);
            $utilisateur->setPassword(''); // OAuth users don't need a password
            $utilisateur->setRole($selectedRole); // Définir le rôle
            $utilisateur->setEstActif(true); // Activer l'utilisateur

            // Persister l'utilisateur en base de données
            $this->entityManager->persist($utilisateur);
            $this->entityManager->flush();

            // Récupérer l'ID après flush
            $userId = $utilisateur->getId();

            // Stocker l'ID d'utilisateur en session pour authentification
            $session->set('_oauth_user_id', $userId);
            $session->set('_oauth_authenticated', true);
            $session->save(); // Sauvegarder la session
            
            // Rediriger vers l'endpoint d'authentification
            return $this->redirectToRoute('oauth_authenticate');
            
        } catch (\Exception $e) {
            // En cas d'erreur, afficher le message et rediriger
            $this->addFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
            return $this->redirectToRoute('google_select_role');
        }
    }

    /**
     * Authentifier l'utilisateur et le rediriger au bon profil
     */
    private function authenticateAndRedirect(Utilisateur $utilisateur, Request $request): Response
    {
        // Créer le token correctement pour Symfony 5.4+
        $token = new UsernamePasswordToken(
            $utilisateur,
            'main',
            $utilisateur->getRoles()
        );
        
        // Stocker le token
        $this->tokenStorage->setToken($token);
        
        // Dispatcher l'événement qui sauvegarde la session
        $event = new InteractiveLoginEvent($request, $token);
        $this->eventDispatcher->dispatch($event);
        
        // Nettoyer les données temporaires
        $request->getSession()->remove('google_user_data');
        
        $this->addFlash('success', 'Connecté!');
        
        // Redirection vers le profil
        return $this->redirectToRoute('app_profile');
    }

    /**
     * Endpoint d'authentification intermédiaire après création d'utilisateur OAuth
     */
    #[Route('/oauth/authenticate', name: 'oauth_authenticate')]
    public function oauthAuthenticate(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('_oauth_user_id');
        $isAuthenticated = $session->get('_oauth_authenticated');
        
        // Vérifier que l'utilisateur est en train de s'authentifier
        if (!$userId || !$isAuthenticated) {
            $this->addFlash('error', 'Authentification invalide');
            return $this->redirectToRoute('app_login');
        }
        
        try {
            // Récupérer l'utilisateur depuis la base de données
            $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->find($userId);
            
            if (!$utilisateur) {
                $this->addFlash('error', 'Utilisateur non trouvé');
                return $this->redirectToRoute('app_login');
            }
            
            // ÉTAPE 1: Nettoyer les données temporaires AVANT de créer le token
            $session->remove('_oauth_user_id');
            $session->remove('_oauth_authenticated');
            $session->remove('google_user_data');
            
            // ÉTAPE 2: Créer le token avec la bonne signature pour Symfony 5.4+
            // Signature: __construct(UserInterface $user, string $firewallName, array $roles)
            $token = new UsernamePasswordToken(
                $utilisateur,
                'main',
                $utilisateur->getRoles()
            );
            
            // ÉTAPE 3: Sauvegarder le token dans TokenStorage
            $this->tokenStorage->setToken($token);
            
            // ÉTAPE 4: Dispatcher l'événement InteractiveLogin
            // Cet événement est CRUCIAL - c'est lui qui persiste le token dans la session
            $event = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch($event);
            
            // ÉTAPE 5: Sauvegarder la session APRÈS dispatch de l'événement
            // Car c'est le dispatch qui modifie la session
            $session->save();
            
            $this->addFlash('success', 'Connecté!');
            
            // ÉTAPE 6: Redirection vers le profil 
            return $this->redirectToRoute('app_profile');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'authentification: ' . $e->getMessage());
            // Log the error for debugging
            error_log('OAuth authentication error: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            return $this->redirectToRoute('app_login');
        }
    }

    /**
     * Crée une instance de la bonne classe utilisateur selon le rôle
     * (Important pour Single Table Inheritance - Doctrine a besoin du discriminator)
     */
    private function createUtilisateurByRole(string $role): Utilisateur
    {
        return match($role) {
            Utilisateur::ROLE_SIMPLE_PATIENT => new Patient(),
            Utilisateur::ROLE_SIMPLE_MEDECIN => new Medecin(),
            Utilisateur::ROLE_SIMPLE_RESPONSABLE_LABO => new ResponsableLaboratoire(),
            Utilisateur::ROLE_SIMPLE_RESPONSABLE_PARA => new ResponsableParapharmacie(),
            default => throw new \InvalidArgumentException("Rôle invalide: $role"),
        };
    }
}
