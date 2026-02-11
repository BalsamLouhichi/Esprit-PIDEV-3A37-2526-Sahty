<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $totalMedecins = $this->userRepo->countByRole('medecin');
        $totalPatients = $this->userRepo->countByRole('patient');
        $totalResponsableLabo = $this->userRepo->countByRole('responsable_labo');
        $totalResponsablePara = $this->userRepo->countByRole('responsable_para');
        $totalInactive = $this->userRepo->countByStatus(false);
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
            'totalResponsableLabo' => $totalResponsableLabo,
            'totalResponsablePara' => $totalResponsablePara,
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
        $search = $request->query->get('search');
        
        // Utilise la méthode de recherche
        if ($search || $role) {
            $utilisateurs = $this->userRepo->search($search, $role);
        } else {
            $utilisateurs = $this->userRepo->findAll();
        }

        return $this->render('admin/users.html.twig', [
            'utilisateurs' => $utilisateurs,
            'selectedRole' => $role,
            'searchQuery' => $search
        ]);
    }

    #[Route('/users/search', name: 'users_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $role = $request->query->get('role');
        $search = $request->query->get('search');
        
        // Utilise la méthode de recherche
        if ($search || $role) {
            $utilisateurs = $this->userRepo->search($search, $role);
        } else {
            $utilisateurs = $this->userRepo->findAll();
        }
        
        // Si c'est une requête AJAX, retourne seulement le tableau HTML
        if ($request->isXmlHttpRequest()) {
            return $this->render('admin/_user_table_rows.html.twig', [
                'utilisateurs' => $utilisateurs
            ]);
        }
        
        // Sinon, redirige vers la page principale
        return $this->redirectToRoute('admin_users');
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

            // Instantiate correct subclass based on role
            $role = $data->get('role');
            switch ($role) {
                case 'medecin':
                    $user = new Medecin();
                    break;
                case 'patient':
                    $user = new Patient();
                    break;
                case 'responsable_labo':
                    $user = new ResponsableLaboratoire();
                    break;
                case 'responsable_para':
                    $user = new ResponsableParapharmacie();
                    break;
                default:
                    $user = new Utilisateur();
            }

            $user->setNom($data->get('nom'));
            $user->setPrenom($data->get('prenom'));
            $user->setEmail($data->get('email'));
            $user->setRole($role ?: $user->getRole());

            // basic extra fields
            if ($tel = $data->get('telephone')) {
                $user->setTelephone($tel);
            }
            if ($dn = $data->get('dateNaissance')) {
                try {
                    $user->setDateNaissance(new \DateTime($dn));
                } catch (\Exception $e) {
                }
            }
            $user->setEstActif($data->get('estActif') ? true : false);

            // password
            $plain = $data->get('password');
            if ($plain) {
                $hashed = $passwordHasher->hashPassword($user, $plain);
                $user->setPassword($hashed);
            }

            // file uploads
            /** @var UploadedFile $photo */
            $photo = $request->files->get('photoUpload');
            if ($photo instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0777, true);
                }
                $filename = uniqid('profile_') . '.' . $photo->guessExtension();
                $photo->move($uploadsDir, $filename);
                $user->setPhotoProfil('/uploads/profiles/' . $filename);
            } elseif ($data->get('photoProfil')) {
                $user->setPhotoProfil($data->get('photoProfil'));
            }

            // role-specific fields
            if ($user instanceof Medecin) {
                $user->setSpecialite($data->get('specialite'));
                $user->setAnneeExperience($data->get('anneeExperience') ? (int)$data->get('anneeExperience') : null);
                $user->setGrade($data->get('grade'));
                $user->setAdresseCabinet($data->get('adresseCabinet'));
                $user->setTelephoneCabinet($data->get('telephoneCabinet'));
                $user->setNomEtablissement($data->get('nomEtablissement'));
                $user->setNumeroUrgence($data->get('numeroUrgence'));
                $user->setDisponibilite($data->get('disponibilite'));

                $doc = $request->files->get('documentPdf');
                if ($doc instanceof UploadedFile) {
                    $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/docs';
                    if (!is_dir($uploadsDir)) {@mkdir($uploadsDir, 0777, true);} 
                    $docName = uniqid('doc_') . '.' . $doc->guessExtension();
                    $doc->move($uploadsDir, $docName);
                    $user->setDocumentPdf('/uploads/docs/' . $docName);
                }
            }

            if ($user instanceof Patient) {
                $user->setGroupeSanguin($data->get('groupeSanguin'));
                $user->setContactUrgence($data->get('contactUrgence'));
                $user->setSexe($data->get('sexe'));
            }

            if ($user instanceof ResponsableLaboratoire) {
                $user->setLaboratoireId($data->get('laboratoireId') ? (int)$data->get('laboratoireId') : null);
            }

            if ($user instanceof ResponsableParapharmacie) {
                $user->setParapharmacieId($data->get('parapharmacieId') ? (int)$data->get('parapharmacieId') : null);
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

            if ($tel = $data->get('telephone')) {
                $user->setTelephone($tel);
            } else {
                $user->setTelephone(null);
            }

            if ($dn = $data->get('dateNaissance')) {
                try {
                    $user->setDateNaissance(new \DateTime($dn));
                } catch (\Exception $e) {
                }
            } else {
                $user->setDateNaissance(null);
            }

            $user->setEstActif($data->get('estActif') ? true : false);

            $plain = $data->get('password');
            if ($plain) {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            // file uploads
            /** @var UploadedFile $photo */
            $photo = $request->files->get('photoUpload');
            if ($photo instanceof UploadedFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                if (!is_dir($uploadsDir)) {@mkdir($uploadsDir, 0777, true);} 
                $filename = uniqid('profile_') . '.' . $photo->guessExtension();
                $photo->move($uploadsDir, $filename);
                $user->setPhotoProfil('/uploads/profiles/' . $filename);
            } elseif ($data->get('photoProfil')) {
                $user->setPhotoProfil($data->get('photoProfil'));
            }

            // role-specific fields
            if ($user instanceof Medecin) {
                $user->setSpecialite($data->get('specialite'));
                $user->setAnneeExperience($data->get('anneeExperience') ? (int)$data->get('anneeExperience') : null);
                $user->setGrade($data->get('grade'));
                $user->setAdresseCabinet($data->get('adresseCabinet'));
                $user->setTelephoneCabinet($data->get('telephoneCabinet'));
                $user->setNomEtablissement($data->get('nomEtablissement'));
                $user->setNumeroUrgence($data->get('numeroUrgence'));
                $user->setDisponibilite($data->get('disponibilite'));

                $doc = $request->files->get('documentPdf');
                if ($doc instanceof UploadedFile) {
                    $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/docs';
                    if (!is_dir($uploadsDir)) {@mkdir($uploadsDir, 0777, true);} 
                    $docName = uniqid('doc_') . '.' . $doc->guessExtension();
                    $doc->move($uploadsDir, $docName);
                    $user->setDocumentPdf('/uploads/docs/' . $docName);
                }
            }

            if ($user instanceof Patient) {
                $user->setGroupeSanguin($data->get('groupeSanguin'));
                $user->setContactUrgence($data->get('contactUrgence'));
                $user->setSexe($data->get('sexe'));
            }

            if ($user instanceof ResponsableLaboratoire) {
                $user->setLaboratoireId($data->get('laboratoireId') ? (int)$data->get('laboratoireId') : null);
            }

            if ($user instanceof ResponsableParapharmacie) {
                $user->setParapharmacieId($data->get('parapharmacieId') ? (int)$data->get('parapharmacieId') : null);
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