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
    private function getAuthenticatedUtilisateur(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('profile/index.html.twig', [
            'user' => $this->getAuthenticatedUtilisateur(),
        ]);
    }

    #[Route('/profile/edit', name: 'app_profile_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $entityManager->getRepository(Utilisateur::class)->find(
            $this->getAuthenticatedUtilisateur()->getId()
        );
        if (!$user instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }

        $form = $this->createForm(ProfileEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photoFile = $form->get('photoProfil')->getData();

            if ($photoFile !== null) {
                $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug((string) $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $photoFile->guessExtension();

                try {
                    $projectDir = $this->getParameter('kernel.project_dir');
                    if (!is_string($projectDir)) {
                        throw new \RuntimeException('Invalid project dir parameter.');
                    }
                    $uploadDirectory = $projectDir . '/public/uploads/profiles';
                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0777, true);
                    }

                    $photoFile->move($uploadDirectory, $newFilename);

                    $existingPhoto = $user->getPhotoProfil();
                    if ($existingPhoto !== null && $existingPhoto !== '') {
                        $oldPhotoPath = $uploadDirectory . '/' . $existingPhoto;
                        if (is_file($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }

                    $user->setPhotoProfil($newFilename);
                } catch (FileException) {
                    $this->addFlash('error', 'Erreur lors du telechargement de la photo.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a ete mis a jour avec succes.');

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

        $user = $entityManager->getRepository(Utilisateur::class)->find(
            $this->getAuthenticatedUtilisateur()->getId()
        );
        if (!$user instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }

        if ($request->isMethod('POST')) {
            $oldPassword = (string) $request->request->get('old_password', '');
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
                $this->addFlash('error', 'L ancien mot de passe est incorrect.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les nouveaux mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caracteres.');
                return $this->redirectToRoute('app_profile_change_password');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a ete change avec succes.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', [
            'user' => $user,
        ]);
    }
}
