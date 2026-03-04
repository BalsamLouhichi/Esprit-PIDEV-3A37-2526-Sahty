<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class DemandeAnalyseNotificationService
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
        private readonly string $fromAddress = 'no-reply@sahty.local'
    ) {
    }

    /**
     * @return array{patient_notified: bool, laboratoire_notified: bool}
     */
    public function notifyCreation(DemandeAnalyse $demandeAnalyse): array
    {
        $patientNotified = $this->notifyPatient($demandeAnalyse);
        $laboratoireNotified = $this->notifyLaboratoire($demandeAnalyse);

        return [
            'patient_notified' => $patientNotified,
            'laboratoire_notified' => $laboratoireNotified,
        ];
    }

    private function notifyPatient(DemandeAnalyse $demandeAnalyse): bool
    {
        $patient = $demandeAnalyse->getPatient();
        if (!$patient) {
            return false;
        }

        $patientEmail = $this->sanitizeEmail($patient->getEmail());
        if ($patientEmail === null) {
            return false;
        }

        $laboratoireNom = $demandeAnalyse->getLaboratoire()?->getNom() ?? 'laboratoire non precise';
        $typeBilan = trim((string) $demandeAnalyse->getTypeBilan());
        if ($typeBilan === '') {
            $typeBilan = 'Analyse medicale';
        }

        $body = sprintf(
            "Bonjour %s,\n\nVotre demande d'analyse a bien ete enregistree.\nType de bilan: %s\nLaboratoire: %s\nStatut: %s\n\nVous recevrez une notification des que le resultat sera disponible.\n",
            $patient->getNomComplet(),
            $typeBilan,
            $laboratoireNom,
            $demandeAnalyse->getStatut()
        );

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($patientEmail)
            ->subject("Confirmation de votre demande d'analyse")
            ->text($body);

        return $this->emailService->send($email);
    }

    private function notifyLaboratoire(DemandeAnalyse $demandeAnalyse): bool
    {
        $laboratoire = $demandeAnalyse->getLaboratoire();
        if (!$laboratoire) {
            return false;
        }

        $recipient = $this->sanitizeEmail($laboratoire->getEmail());
        if ($recipient === null) {
            $recipient = $this->sanitizeEmail($laboratoire->getEmailResponsable());
        }
        if ($recipient === null) {
            return false;
        }

        $patientName = $demandeAnalyse->getPatient()?->getNomComplet() ?? 'Patient inconnu';
        $typeBilan = trim((string) $demandeAnalyse->getTypeBilan());
        if ($typeBilan === '') {
            $typeBilan = 'Analyse medicale';
        }

        $body = sprintf(
            "Nouvelle demande d'analyse recue.\n\nPatient: %s\nType de bilan: %s\nDate de demande: %s\nStatut: %s\n",
            $patientName,
            $typeBilan,
            $demandeAnalyse->getDateDemande()->format('d/m/Y H:i'),
            $demandeAnalyse->getStatut()
        );

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($recipient)
            ->subject("Nouvelle demande d'analyse a traiter")
            ->text($body);

        return $this->emailService->send($email);
    }

    private function sanitizeEmail(?string $email): ?string
    {
        $value = trim((string) $email);
        if ($value === '') {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Adresse email invalide ignoree lors de la notification de demande analyse.', [
                'email' => $value,
            ]);
            return null;
        }

        return $value;
    }
}
