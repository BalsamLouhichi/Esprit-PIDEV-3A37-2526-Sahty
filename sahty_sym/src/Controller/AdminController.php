<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    private UtilisateurRepository $userRepo;
    private EntityManagerInterface $em;

    public function __construct(UtilisateurRepository $userRepo, EntityManagerInterface $em)
    {
        $this->userRepo = $userRepo;
        $this->em = $em;
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get basic statistics
        $totalUsers = $this->userRepo->count([]);
        $totalMedecins = $this->userRepo->count(['role' => 'medecin']);
        $totalPatients = $this->userRepo->count(['role' => 'patient']);
        $totalResponsableLabo = $this->userRepo->count(['role' => 'responsable_labo']);
        $totalResponsablePara = $this->userRepo->count(['role' => 'responsable_para']);
        $totalInactive = $this->userRepo->count(['estActif' => false]);
        $totalActive = $totalUsers - $totalInactive;

        // Calculate user distribution percentages
        $doctorsPercent = $totalUsers > 0 ? round(($totalMedecins / $totalUsers) * 100) : 0;
        $patientsPercent = $totalUsers > 0 ? round(($totalPatients / $totalUsers) * 100) : 0;
        $staffPercent = $totalUsers > 0 ? round((($totalResponsableLabo + $totalResponsablePara) / $totalUsers) * 100) : 0;
        $adminPercent = 100 - ($doctorsPercent + $patientsPercent + $staffPercent);

        // Get recent users
        $recentUsers = $this->userRepo->findBy([], ['creeLe' => 'DESC'], 5);

        return $this->render('admin/index.html.twig', [
            'totalUsers' => $totalUsers,
            'totalMedecins' => $totalMedecins,
            'totalPatients' => $totalPatients,
            'totalInactive' => $totalInactive,
            'totalActive' => $totalActive,
            'stats' => [
                'total_users' => $totalUsers,
                'active_doctors' => $totalMedecins,
                'todays_appointments' => 0,
                'monthly_revenue' => 0,
                'pending_appointments' => 0,
                'todays_patients' => $totalPatients,
                'available_doctors' => $totalMedecins,
                'emergency_cases' => 0,
                'pending_bills' => 0,
                'unread_notifications' => 0,
                'weekly_appointments' => [
                    'mon' => 45,
                    'tue' => 52,
                    'wed' => 48,
                    'thu' => 55,
                    'fri' => 60,
                ],
            ],
            'system_status' => [
                'server_load' => 65,
                'database_usage' => 42,
                'storage' => 78,
                'overall' => 'operational',
            ],
            'user_distribution' => [
                'doctors' => $doctorsPercent,
                'patients' => $patientsPercent,
                'staff' => $staffPercent,
                'admin' => $adminPercent,
            ],
            'recent_appointments' => [],
            'recent_activities' => [],
            'recent_users' => $recentUsers,
            'app_name' => 'Sahty',
            'app_version' => '1.0.0',
        ]);
    }

    #[Route('/users', name: 'users')]
    public function users(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $role = $request->query->get('role');
        
        if ($role) {
            $utilisateurs = $this->userRepo->findBy(['role' => $role]);
        } else {
            $utilisateurs = $this->userRepo->findAll();
        }

        return $this->render('admin/users.html.twig', [
            'utilisateurs' => $utilisateurs,
            'selectedRole' => $role,
        ]);
    }

    #[Route('/users/new', name: 'user_new')]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_user_new');
            }

            $data = $request->request;

            $user = new Utilisateur();
            $user->setNom($data->get('nom'));
            $user->setPrenom($data->get('prenom'));
            $user->setEmail($data->get('email'));
            $user->setRole($data->get('role'));

            $plain = $data->get('password');
            if ($plain) {
                $hashed = $passwordHasher->hashPassword($user, $plain);
                $user->setPassword($hashed);
            }

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_form.html.twig', [
            'user' => null,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'user_edit')]
    public function edit(Request $request, int $id, UserPasswordHasherInterface $passwordHasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }

        if ($request->isMethod('POST')) {
            // Validate CSRF token
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('form', $token)) {
                $this->addFlash('danger', 'Jeton CSRF invalide.');
                return $this->redirectToRoute('admin_user_edit', ['id' => $id]);
            }

            $data = $request->request;
            $user->setNom($data->get('nom'));
            $user->setPrenom($data->get('prenom'));
            $user->setEmail($data->get('email'));
            $user->setRole($data->get('role'));

            $plain = $data->get('password');
            if ($plain) {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            $this->em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user_form.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-user'.$user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_users');
        }

        $this->em->remove($user);
        $this->em->flush();

        $this->addFlash('success', 'Utilisateur supprimé.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/toggle-status', name: 'user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('admin_users');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle-user'.$user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_users');
        }

        // Toggle the status
        $user->setEstActif(!$user->isEstActif());
        $this->em->flush();

        $status = $user->isEstActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Utilisateur $status.");
        return $this->redirectToRoute('admin_users');
    }
}
