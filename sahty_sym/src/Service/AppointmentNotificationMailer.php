<?php

namespace App\Service;

use App\Entity\RendezVous;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AppointmentNotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromAddress
    ) {
    }

    public function sendConfirmationToPatient(RendezVous $rdv): void
    {
        $patientEmail = $rdv->getPatient()?->getEmail();
        if (!$patientEmail) {
            return;
        }

        $patientName = $rdv->getPatient()?->getNomComplet() ?: 'Patient';
        $medecinName = $rdv->getMedecin()?->getNomComplet() ?: 'Medecin';
        $dateRdv = $rdv->getDateRdv()?->format('d/m/Y') ?: '-';
        $heureRdv = $rdv->getHeureRdv()?->format('H:i') ?: '-';
        $typeConsultation = $rdv->getTypeConsultation();
        $meetingUrl = $rdv->getMeetingUrl();
        $isOnline = $typeConsultation === 'en_ligne' && $meetingUrl;

        $safePatientName = htmlspecialchars($patientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMedecinName = htmlspecialchars($medecinName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeDateRdv = htmlspecialchars($dateRdv, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHeureRdv = htmlspecialchars($heureRdv, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMeetingUrl = htmlspecialchars((string) $meetingUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $textBody = "Bonjour {$patientName},\n\n"
            . "Votre rendez-vous a ete confirme par le medecin.\n"
            . "Medecin: Dr. {$medecinName}\n"
            . "Date: {$dateRdv}\n"
            . "Heure: {$heureRdv}\n";

        if ($isOnline) {
            $textBody .= "Type: Consultation en ligne\n"
                . "Lien Meet: {$meetingUrl}\n";
        } else {
            $textBody .= "Type: Consultation au cabinet\n";
        }

        $textBody .= "\nCordialement,\nEquipe Sahty";

        $meetingBlockHtml = '';
        if ($isOnline) {
            $meetingBlockHtml = <<<HTML
            <p style="margin:0 0 10px 0;"><strong>Type :</strong> Consultation en ligne</p>
            <p style="margin:0 0 10px 0;"><strong>Lien Meet :</strong></p>
            <p style="margin:0 0 18px 0;">
                <a href="{$safeMeetingUrl}" style="color:#0f4c81;font-weight:700;">{$safeMeetingUrl}</a>
            </p>
HTML;
        } else {
            $meetingBlockHtml = '<p style="margin:0 0 18px 0;"><strong>Type :</strong> Consultation au cabinet</p>';
        }

        $htmlBody = <<<HTML
<div style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,sans-serif;color:#102a43;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d9e2ec;border-radius:12px;overflow:hidden;">
        <div style="background:#0f4c81;color:#ffffff;padding:18px 24px;">
            <div style="font-size:18px;font-weight:700;">Rendez-vous confirme</div>
            <div style="font-size:13px;opacity:.9;">Sahty</div>
        </div>
        <div style="padding:24px;">
            <p style="margin:0 0 12px 0;">Bonjour <strong>{$safePatientName}</strong>,</p>
            <p style="margin:0 0 12px 0;">Votre rendez-vous a ete confirme par le medecin.</p>
            <p style="margin:0 0 10px 0;"><strong>Medecin :</strong> Dr. {$safeMedecinName}</p>
            <p style="margin:0 0 10px 0;"><strong>Date :</strong> {$safeDateRdv}</p>
            <p style="margin:0 0 10px 0;"><strong>Heure :</strong> {$safeHeureRdv}</p>
            {$meetingBlockHtml}
            <p style="margin:0;">Merci de votre confiance.</p>
        </div>
    </div>
</div>
HTML;

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($patientEmail)
            ->subject('Confirmation de votre rendez-vous')
            ->text($textBody)
            ->html($htmlBody);

        $this->mailer->send($email);
    }

    public function sendCancellationToPatient(RendezVous $rdv): void
    {
        $patientEmail = $rdv->getPatient()?->getEmail();
        if (!$patientEmail) {
            return;
        }

        $patientName = $rdv->getPatient()?->getNomComplet() ?: 'Patient';
        $medecinName = $rdv->getMedecin()?->getNomComplet() ?: 'Medecin';
        $dateRdv = $rdv->getDateRdv()?->format('d/m/Y') ?: '-';
        $heureRdv = $rdv->getHeureRdv()?->format('H:i') ?: '-';

        $safePatientName = htmlspecialchars($patientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMedecinName = htmlspecialchars($medecinName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeDateRdv = htmlspecialchars($dateRdv, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHeureRdv = htmlspecialchars($heureRdv, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $textBody = "Bonjour {$patientName},\n\n"
            . "Votre rendez-vous a ete annule par le medecin.\n"
            . "Medecin: Dr. {$medecinName}\n"
            . "Date initiale: {$dateRdv}\n"
            . "Heure initiale: {$heureRdv}\n\n"
            . "Veuillez reprendre rendez-vous si necessaire.\n\n"
            . "Cordialement,\n"
            . "Equipe Sahty";

        $htmlBody = <<<HTML
<div style="margin:0;padding:24px;background:#f4f7fb;font-family:Arial,sans-serif;color:#102a43;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d9e2ec;border-radius:12px;overflow:hidden;">
        <div style="background:#9b1c1c;color:#ffffff;padding:18px 24px;">
            <div style="font-size:18px;font-weight:700;">Rendez-vous annule</div>
            <div style="font-size:13px;opacity:.9;">Sahty</div>
        </div>
        <div style="padding:24px;">
            <p style="margin:0 0 12px 0;">Bonjour <strong>{$safePatientName}</strong>,</p>
            <p style="margin:0 0 12px 0;">Votre rendez-vous a ete annule par le medecin.</p>
            <p style="margin:0 0 10px 0;"><strong>Medecin :</strong> Dr. {$safeMedecinName}</p>
            <p style="margin:0 0 10px 0;"><strong>Date initiale :</strong> {$safeDateRdv}</p>
            <p style="margin:0 0 18px 0;"><strong>Heure initiale :</strong> {$safeHeureRdv}</p>
            <p style="margin:0;">Veuillez reprendre rendez-vous si necessaire.</p>
        </div>
    </div>
</div>
HTML;

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($patientEmail)
            ->subject('Annulation de votre rendez-vous')
            ->text($textBody)
            ->html($htmlBody);

        $this->mailer->send($email);
    }
}
