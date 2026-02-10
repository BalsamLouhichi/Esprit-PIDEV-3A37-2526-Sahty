<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // ✅ Si l'utilisateur est déjà connecté
        if ($this->getUser()) {
            // 🔐 Si c'est un administrateur, rediriger vers le dashboard admin
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_index');
            }
           
            // ✅ Autres utilisateurs
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('securityL/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]); 
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the firewall.');
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        // 🔐 Sécurité
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

      
       

        return $this->render('profile/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
   
}
