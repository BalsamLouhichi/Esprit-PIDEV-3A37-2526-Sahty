<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class EventRegistrationEmailService
{
    private MailerInterface $mailer;
    private string $mailFrom;
    private string $appUrl;
    private string $mailerDsn;

    public function __construct(
        MailerInterface $mailer,
        string $mailFrom = 'no-reply@sahty.local',
        string $appUrl = 'http://127.0.0.1:8000',
        string $mailerDsn = 'null://null'
    ) {
        $this->mailer = $mailer;
        $this->mailFrom = $mailFrom;
        $this->appUrl = $appUrl;
        $this->mailerDsn = $mailerDsn;
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

    public function sendApprovalToCreator(Evenement $evenement): bool
    {
        $creator = $evenement->getCreateur();
        $recipient = $creator?->getEmail();
        if (!$recipient) {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from($this->mailFrom)
            ->to($recipient)
            ->subject('Votre demande d\'evenement a ete approuvee - ' . (string) $evenement->getTitre())
            ->htmlTemplate('emails/evenement_demande_approuvee.html.twig')
            ->context([
                'evenement' => $evenement,
                'utilisateur' => $creator,
                'eventUrl' => rtrim($this->appUrl, '/') . '/evenements/' . $evenement->getId() . '/client-view',
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
