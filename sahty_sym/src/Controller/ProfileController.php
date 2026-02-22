<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\ProfileEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit')]
    public function edit(
        Request $request, 
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var Utilisateur $user */
        $user = $this->getUser();
        
        // IMPORTANT: Récupérer l'utilisateur depuis la base pour éviter les problèmes de proxy
        $user = $entityManager->getRepository(Utilisateur::class)->find($user->getId());
        
        $form = $this->createForm(ProfileEditType::class, $user);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            // ===========================================
            // GESTION DE L'UPLOAD DE LA PHOTO
            // ===========================================
            $photoFile = $form->get('photoProfil')->getData();
            
            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                
                // Nettoyer le nom du fichier
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();
                
                try {
                    // Créer le dossier s'il n'existe pas
                    $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/profiles';
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0777, true);
                    }
                    
                    // Déplacer le fichier
                    $photoFile->move(
                        $uploadDirectory,
                        $newFilename
                    );
                    
                    // Supprimer l'ancienne photo si elle existe
                    if ($user->getPhotoProfil()) {
                        $oldPhotoPath = $uploadDirectory . '/' . $user->getPhotoProfil();
                        if (file_exists($oldPhotoPath) && is_file($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    
                    // Mettre à jour le nom du fichier dans l'entité
                    $user->setPhotoProfil($newFilename);
                    
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de la photo.');
                }
            }
            
            // ===========================================
            // SAUVEGARDER LES MODIFICATIONS
            // ===========================================
            // Pas besoin de persist() pour un update, flush() suffit
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }
        
        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/profile/change-password', name: 'app_profile_change_password')]
    public function changePassword(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var Utilisateur $user */
        $user = $this->getUser();
        
        // Récupérer l'utilisateur depuis la base
        $user = $entityManager->getRepository(Utilisateur::class)->find($user->getId());
        
        if ($request->isMethod('POST')) {
            $oldPassword = $request->request->get('old_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');
            
            // Vérifier l'ancien mot de passe
            if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                $this->addFlash('error', 'L\'ancien mot de passe est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }
            
            // Vérifier que les nouveaux mots de passe correspondent
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_profile_change_password');
            }
            
            // Vérifier la longueur du mot de passe
            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                return $this->redirectToRoute('app_profile_change_password');
            }
            
            // Hash et sauvegarde du nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            $entityManager->flush();
            
            $this->addFlash('success', 'Votre mot de passe a été changé avec succès.');
            return $this->redirectToRoute('app_profile');
        }
        
        return $this->render('profile/change_password.html.twig', [
            'user' => $user,
        ]);
    }
}