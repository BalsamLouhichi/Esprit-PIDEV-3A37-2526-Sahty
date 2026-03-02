<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\PasswordResetToken;
use App\Repository\UtilisateurRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

class PasswordResetController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UtilisateurRepository $utilisateurRepository;
    private PasswordResetTokenRepository $passwordResetRepository;
    private EmailService $emailService;
    private UserPasswordHasherInterface $passwordHasher;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UtilisateurRepository $utilisateurRepository,
        PasswordResetTokenRepository $passwordResetRepository,
        EmailService $emailService,
        UserPasswordHasherInterface $passwordHasher,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->utilisateurRepository = $utilisateurRepository;
        $this->passwordResetRepository = $passwordResetRepository;
        $this->emailService = $emailService;
        $this->passwordHasher = $passwordHasher;
        $this->logger = $logger;
    }

    /**
     * Formulaire de demande de réinitialisation de mot de passe
     */
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('forgot_password', $submittedToken)) {
                $this->addFlash('error', 'Votre session a expiré. Merci de réessayer.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $email = strtolower(trim((string) $request->request->get('email')));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Adresse email invalide.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Chercher l'utilisateur par email
            $utilisateur = $this->utilisateurRepository->findOneBy(['email' => $email]);

            if ($utilisateur) {
                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                
                // Supprimer les anciens tokens non utilisés pour cet utilisateur
                $oldTokens = $this->entityManager->getRepository(PasswordResetToken::class)
                    ->findBy(['utilisateur' => $utilisateur, 'isUsed' => false]);
                
                foreach ($oldTokens as $oldToken) {
                    $this->entityManager->remove($oldToken);
                }
                $this->entityManager->flush();

                // Créer un nouveau token de réinitialisation (valable 24h)
                $now = new \DateTime();
                $passwordResetToken = new PasswordResetToken();
                $passwordResetToken->setToken($token);
                $passwordResetToken->setUtilisateur($utilisateur);
                $passwordResetToken->setCreatedAt($now);
                $passwordResetToken->setExpiresAt($now->modify('+24 hours'));

                $this->entityManager->persist($passwordResetToken);
                $this->entityManager->flush();

                // Envoyer l'email avec le lien
                $this->sendResetEmail($utilisateur, $token);

            }

            // Ne pas révéler si l'email existe ou pas (sécurité)
            $this->addFlash('success', 'Si un compte existe, un email de réinitialisation a été envoyé.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('forgot_password.html.twig');
    }

    /**
     * Formulaire de réinitialisation avec le token
     */
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response
    {
        // Chercher le token valide
        $passwordResetToken = $this->passwordResetRepository->findValidTokenByToken($token);

        if (!$passwordResetToken) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('reset_password', $submittedToken)) {
                $this->addFlash('error', 'Votre session a expiré. Merci de réessayer.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            $newPassword = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('password_confirm');

            // Valider les mots de passe
            if ($newPassword === '' || $confirmPassword === '') {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password', ['token' => $token]);
            }

            // Mettre à jour le mot de passe
            $utilisateur = $passwordResetToken->getUtilisateur();
            $hashedPassword = $this->passwordHasher->hashPassword($utilisateur, $newPassword);
            $utilisateur->setPassword($hashedPassword);

            // Marquer le token comme utilisé
            $passwordResetToken->setIsUsed(true);

            $this->entityManager->persist($utilisateur);
            $this->entityManager->persist($passwordResetToken);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password.html.twig', [
            'token' => $token,
        ]);
    }

    /**
     * Envoyer l'email de réinitialisation
     */
    private function sendResetEmail(Utilisateur $utilisateur, string $token): void
    {
        try {
            $resetLink = $this->generateUrl('app_reset_password', [
                'token' => $token,
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $this->logger->info('Préparation de l\'email de réinitialisation', [
                'email' => $utilisateur->getEmail(),
                'utilisateur_id' => $utilisateur->getId(),
            ]);

            $email = (new Email())
                ->from('maramouerghi1234@gmail.com')
                ->to($utilisateur->getEmail())
                ->subject('MEDINOVA - Réinitialisation de votre mot de passe')
                ->html($this->renderView('emails/reset_password.html.twig', [
                    'utilisateur' => $utilisateur,
                    'resetLink' => $resetLink,
                    'expiresIn' => '24 heures',
                ]));

            // Utiliser le service postal
            $success = $this->emailService->send($email);
            
            if ($success) {
                $this->logger->info('Email de réinitialisation envoyé avec succès', [
                    'email' => $utilisateur->getEmail(),
                ]);
            } else {
                $this->logger->warning('Email de réinitialisation: échec possible de l\'envoi', [
                    'email' => $utilisateur->getEmail(),
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Exception lors de l\'envoi de l\'email de réinitialisation', [
                'email' => $utilisateur->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
