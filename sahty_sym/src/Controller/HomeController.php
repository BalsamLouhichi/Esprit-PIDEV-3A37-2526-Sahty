<?php

namespace App\Controller;

// Keep ALL your event-related imports
use App\Entity\Evenement;
use App\Entity\Utilisateur;
use App\Entity\InscriptionEvenement;
use App\Repository\EvenementRepository;

// Add Balsam's additional entity imports (they might be useful)
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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
    public function dashboard(EntityManagerInterface $em, EvenementRepository $evenementRepository): Response
    {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        $now = new \DateTime();
        $lastWeek = clone $now;
        $lastWeek->modify('-7 days');

        try {
            // Statistiques principales
            $totalEvenements = $evenementRepository->count([]);
            
            $evenementsAVenir = $evenementRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.dateDebut > :now')
                ->setParameter('now', $now)
                ->getQuery()
                ->getSingleScalarResult();

            $evenementsSemaine = $evenementRepository->createQueryBuilder('e')
                ->select('COUNT(e.id)')
                ->where('e.creeLe >= :lastWeek')
                ->setParameter('lastWeek', $lastWeek)
                ->getQuery()
                ->getSingleScalarResult();

            $totalInscriptions = $em->getRepository(InscriptionEvenement::class)->count([]);

            // Événements récents
            $evenementsRecents = $evenementRepository->createQueryBuilder('e')
                ->orderBy('e.creeLe', 'DESC')
                ->setMaxResults(6)
                ->getQuery()
                ->getResult();

            // Événements à venir
            $evenementsProchains = $evenementRepository->createQueryBuilder('e')
                ->where('e.dateDebut > :now')
                ->setParameter('now', $now)
                ->orderBy('e.dateDebut', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            // Statistiques par type
            $evenementsParType = $evenementRepository->createQueryBuilder('e')
                ->select('e.type, COUNT(e.id) as count')
                ->groupBy('e.type')
                ->getQuery()
                ->getResult();

        } catch (\Exception $e) {
            // En cas d'erreur, utiliser des valeurs par défaut
            $totalEvenements = 0;
            $evenementsAVenir = 0;
            $evenementsSemaine = 0;
            $totalInscriptions = 0;
            $evenementsRecents = [];
            $evenementsProchains = [];
            $evenementsParType = [];
        }

        return $this->render('evenement/dashboard.html.twig', [
            'totalEvenements' => $totalEvenements,
            'evenementsAVenir' => $evenementsAVenir,
            'evenementsSemaine' => $evenementsSemaine,
            'totalInscriptions' => $totalInscriptions,
            'evenementsRecents' => $evenementsRecents,
            'evenementsProchains' => $evenementsProchains,
            'evenementsParType' => $evenementsParType,
            'user' => $user,
        ]);
        return $this->render('admin_dashboard/index.html.twig');

    }

    // Note: Balsam had a duplicate profil() method - we keep yours only
    #[Route('/profil', name: 'app_profile')]
    public function profil(): Response
    {
        return $this->render('profile.html.twig');
    }

    #[Route('/home', name: 'app_home')]
    public function appHome(): Response
    {
        return $this->render('home/index.html.twig');
    }

}