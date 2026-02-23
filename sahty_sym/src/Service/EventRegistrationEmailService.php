<?php

namespace App\Service;

use App\Entity\InscriptionEvenement;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class EventRegistrationEmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom = 'no-reply@sahty.local',
        private readonly string $appUrl = 'http://127.0.0.1:8000',
        private readonly string $mailerDsn = 'null://null'
    ) {
    }

    public function sendConfirmation(InscriptionEvenement $inscription): bool
    {
        $recipient = $inscription->getUtilisateur()?->getEmail();
        $event = $inscription->getEvenement();
        if (!$recipient || !$event) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from($this->mailFrom)
            ->to($recipient)
            ->subject('Confirmation d\'inscription - ' . (string) $event->getTitre())
            ->htmlTemplate('emails/evenement_inscription_confirmation.html.twig')
            ->context([
                'inscription' => $inscription,
                'evenement' => $event,
                'utilisateur' => $inscription->getUtilisateur(),
                'eventUrl' => rtrim($this->appUrl, '/') . '/evenements/' . $event->getId() . '/client-view',
                'is_simulated_mail' => $this->isSimulationMode(),
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            error_log('[Sahty][MAIL_ERROR] ' . $e->getMessage());
            return false;
        }
    }

    public function sendCancellation(InscriptionEvenement $inscription): bool
    {
        $recipient = $inscription->getUtilisateur()?->getEmail();
        $event = $inscription->getEvenement();
        if (!$recipient || !$event) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from($this->mailFrom)
            ->to($recipient)
            ->subject('Desinscription confirmee - ' . (string) $event->getTitre())
            ->htmlTemplate('emails/evenement_desinscription_confirmation.html.twig')
            ->context([
                'inscription' => $inscription,
                'evenement' => $event,
                'utilisateur' => $inscription->getUtilisateur(),
                'eventUrl' => rtrim($this->appUrl, '/') . '/evenements/' . $event->getId() . '/client-view',
                'is_simulated_mail' => $this->isSimulationMode(),
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            error_log('[Sahty][MAIL_ERROR] ' . $e->getMessage());
            return false;
        }
    }

    public function isSimulationMode(): bool
    {
        return str_starts_with(trim($this->mailerDsn), 'null://');
    }
}
