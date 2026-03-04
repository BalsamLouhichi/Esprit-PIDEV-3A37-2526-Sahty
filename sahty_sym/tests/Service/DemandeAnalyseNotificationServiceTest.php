<?php

namespace App\Tests\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\Laboratoire;
use App\Entity\Patient;
use App\Service\DemandeAnalyseNotificationService;
use App\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class DemandeAnalyseNotificationServiceTest extends TestCase
{
    // Cas nominal: un patient et un laboratoire ont des emails valides.
    // On attend 2 envois: 1 confirmation patient + 1 notification laboratoire.
    public function testNotifyCreationSendsMailToPatientAndLaboratoire(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Verification de la sequence d'envoi et des destinataires.
        $emailService->expects($this->exactly(2))
            ->method('send')
            ->withConsecutive(
                [$this->callback(function (Email $email): bool {
                    $to = $email->getTo();
                    return isset($to[0]) && $to[0] instanceof Address && $to[0]->getAddress() === 'patient@example.tn';
                })],
                [$this->callback(function (Email $email): bool {
                    $to = $email->getTo();
                    return isset($to[0]) && $to[0] instanceof Address && $to[0]->getAddress() === 'labo@example.tn';
                })]
            )
            ->willReturn(true);

        $service = new DemandeAnalyseNotificationService($emailService, $logger);

        $patient = (new Patient())
            ->setPrenom('Ali')
            ->setNom('Ben Salem')
            ->setEmail('patient@example.tn');

        $laboratoire = (new Laboratoire())
            ->setNom('BioLab')
            ->setEmail('labo@example.tn');

        $demande = (new DemandeAnalyse())
            ->setPatient($patient)
            ->setLaboratoire($laboratoire)
            ->setTypeBilan('Bilan sanguin')
            ->setStatut('en_attente');

        $result = $service->notifyCreation($demande);

        // Les deux notifications doivent etre marquees comme envoyees.
        $this->assertTrue($result['patient_notified']);
        $this->assertTrue($result['laboratoire_notified']);
    }

    // Cas degrade: emails invalides ou absents.
    // Aucun email ne doit etre envoye.
    public function testNotifyCreationSkipsInvalidRecipients(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $emailService->expects($this->never())->method('send');

        $service = new DemandeAnalyseNotificationService($emailService, $logger);

        $patient = (new Patient())
            ->setPrenom('Aya')
            ->setNom('Trabelsi')
            ->setEmail('bad-email');

        $laboratoire = (new Laboratoire())
            ->setNom('Lab sans mail');

        $demande = (new DemandeAnalyse())
            ->setPatient($patient)
            ->setLaboratoire($laboratoire);

        $result = $service->notifyCreation($demande);

        // Le service signale explicitement que rien n'a ete notifie.
        $this->assertFalse($result['patient_notified']);
        $this->assertFalse($result['laboratoire_notified']);
    }
}
