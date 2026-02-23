<?php

namespace App\Controller;

use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // ✅ Si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_login_redirect');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('securityL/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    

    

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the firewall.');
    }

    // ========== NEW ROUTES FROM BALSAM ==========

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        // 🔐 Sécurité
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('profile/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/login/redirect', name: 'app_login_redirect')]
    public function loginRedirect(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin');
        }

        if ($this->isGranted('ROLE_RESPONSABLE_LABO')) {
            $user = $this->getUser();
            if ($user instanceof ResponsableLaboratoire && !$user->getLaboratoire()) {
                return $this->redirectToRoute('app_labo_new');
            }

            return $this->redirectToRoute('app_demande_analyse_index');
        }

        // MODIFICATION : Redirection pour responsable parapharmacie
        if ($this->isGranted('ROLE_RESPONSABLE_PARA')) {
            $user = $this->getUser();   // ⚠️ tu dois l’ajouter ici

            if ($user instanceof ResponsableParapharmacie) {

                if ($user->isPremiereConnexion() || !$user->getParapharmacie()) {
                    return $this->redirectToRoute('app_responsable_para_configurer');
                }

                return $this->redirectToRoute('app_responsable_dashboard');
            }
        }

        if ($this->isGranted('ROLE_MEDECIN')) {
            return $this->redirectToRoute('app_demande_analyse_index');
        }

        if ($this->isGranted('ROLE_PATIENT')) {
            return $this->redirectToRoute('app_profile');
        }

        return $this->redirectToRoute('app_profile');
    }
}
