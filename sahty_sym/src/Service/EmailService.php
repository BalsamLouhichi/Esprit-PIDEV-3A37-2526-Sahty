<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class EmailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * Envoyer un email avec gestion d'erreur complète
     */
    public function send(Email $email): bool
    {
        try {
            $this->logger->info('Envoi d\'un email', [
                'to' => implode(', ', array_keys((array)$email->getTo())),
                'subject' => $email->getSubject(),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Email envoyé avec succès', [
                'to' => implode(', ', array_keys((array)$email->getTo())),
                'subject' => $email->getSubject(),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi du mail', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'to' => implode(', ', array_keys((array)$email->getTo())),
            ]);

            return false;
        }
    }

    /**
     * Envoyer un email avec une pièce jointe PDF
     */
    /**
     * @param list<string> $toEmails
     */
    public function sendWithAttachment(
        string $fromEmail,
        array $toEmails,
        string $subject,
        string $textContent,
        string $filePath,
        string $fileName = 'document.pdf'
    ): bool
    {
        try {
            if (!is_file($filePath)) {
                $this->logger->error('Fichier attaché introuvable', [
                    'file_path' => $filePath,
                ]);
                return false;
            }

            $email = (new Email())
                ->from($fromEmail)
                ->to(...$toEmails)
                ->subject($subject)
                ->text($textContent)
                ->attachFromPath($filePath, $fileName, 'application/pdf');

            return $this->send($email);

        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la préparation de l\'email avec pièce jointe', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return false;
        }
    }
}
