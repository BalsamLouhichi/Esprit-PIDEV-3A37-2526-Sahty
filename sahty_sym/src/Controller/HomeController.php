<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

   


    #[Route('/forgot', name: 'app_forgot_password')]
    public function forgot(): Response
    {
        return $this->render('forgot_password.html.twig');
    }

    #[Route('/admin_dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/profil', name: 'app_profile')]
    public function profil(): Response
    {
        return $this->render('profile.html.twig');
    }
}
