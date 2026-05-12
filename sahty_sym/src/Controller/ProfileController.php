<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\ProfileEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

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
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $securityUser = $this->getUser();
        if (!$securityUser instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        // Récupérer l'utilisateur depuis la base pour éviter les problèmes de proxy.
        $user = $entityManager->getRepository(Utilisateur::class)->find($securityUser->getId());
        if (!$user instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $form = $this->createForm(ProfileEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoProfil')->getData();

            if ($photoFile) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();
                $uploadRelativeDirectory = 'uploads/profiles';
                $uploadDirectory = $this->getParameter('kernel.project_dir') . '/public/' . $uploadRelativeDirectory;

                try {
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0777, true);
                    }

                    $photoFile->move($uploadDirectory, $newFilename);

                    if ($user->getPhotoProfil()) {
                        $storedPhotoPath = ltrim((string) $user->getPhotoProfil(), '/');
                        $oldPhotoPath = str_starts_with($storedPhotoPath, 'uploads/')
                            ? $this->getParameter('kernel.project_dir') . '/public/' . $storedPhotoPath
                            : $uploadDirectory . '/' . $storedPhotoPath;

                        if (is_file($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }

                    $user->setPhotoProfil($uploadRelativeDirectory . '/' . $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de la photo.');
                }
            }

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

        $securityUser = $this->getUser();
        if (!$securityUser instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        $user = $entityManager->getRepository(Utilisateur::class)->find($securityUser->getId());
        if (!$user instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        if ($request->isMethod('POST')) {
            $oldPassword = $request->request->get('old_password');
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                $this->addFlash('error', 'L\'ancien mot de passe est incorrect.');

                return $this->redirectToRoute('app_profile_change_password');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');

                return $this->redirectToRoute('app_profile_change_password');
            }

            if (strlen((string) $newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');

                return $this->redirectToRoute('app_profile_change_password');
            }

            $hashedPassword = $passwordHasher->hashPassword($user, (string) $newPassword);
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
