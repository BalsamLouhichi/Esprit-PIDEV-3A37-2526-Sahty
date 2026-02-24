<?php

namespace App\Controller;

use App\Entity\DemandeAnalyse;
use App\Entity\ResultatAnalyse;
use App\Entity\ResponsableLaboratoire;
use App\Form\LaboratoireType;
use App\Integration\FastApiLabAiClient;
use App\Repository\DemandeAnalyseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/responsable-labo')]
class ResponsableLaboratoireController extends AbstractController
{
    #[Route('/demandes', name: 'app_responsable_labo_demandes', methods: ['GET'])]
    public function demandes(
        Request $request,
        DemandeAnalyseRepository $demandeAnalyseRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        [$laboratoire, $demandes, $typeBilanOptions, $filters, $stats, $pagination] = $this->buildDemandesViewData(
            $request,
            $demandeAnalyseRepository,
            $entityManager
        );

        if (!$laboratoire) {
            $this->addFlash('warning', 'Aucun laboratoire associe a votre compte.');
        }

        return $this->render('responsable_laboratoire/demandes.html.twig', [
            'demandes' => $demandes,
            'laboratoire' => $laboratoire,
            'statut_filter' => $filters['statut'],
            'type_bilan_filter' => $filters['type_bilan'],
            'priorite_filter' => $filters['priorite'],
            'date_filter' => $filters['date'],
            'sort' => $filters['sort'],
            'dir' => $filters['dir'],
            'type_bilan_options' => $typeBilanOptions,
            'stats' => $stats,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/demandes/filter', name: 'app_responsable_labo_demandes_filter', methods: ['GET'])]
    public function demandesFilter(
        Request $request,
        DemandeAnalyseRepository $demandeAnalyseRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        [$laboratoire, $demandes, $typeBilanOptions, $filters, $stats, $pagination] = $this->buildDemandesViewData(
            $request,
            $demandeAnalyseRepository,
            $entityManager
        );

        $tableHtml = $this->renderView('responsable_labo/_demandes_table.html.twig', [
            'demandes' => $demandes,
        ]);

        $statsHtml = $this->renderView('responsable_labo/_stats_cards.html.twig', [
            'stats' => $stats,
        ]);

        return $this->json([
            'table' => $tableHtml,
            'stats' => $statsHtml,
        ]);
    }

    #[Route('/laboratoire/edit', name: 'app_responsable_labo_edit', methods: ['GET', 'POST'])]
    public function editLaboratoire(Request $request, EntityManagerInterface $entityManager): Response
    {
        $responsable = $this->getUser();
        if (!$responsable instanceof ResponsableLaboratoire) {
            throw new AccessDeniedException('Acces reserve au responsable laboratoire.');
        }

        $laboratoire = $responsable->getLaboratoire();
        if (!$laboratoire) {
            $this->addFlash('warning', 'Aucun laboratoire associe a votre compte.');
            return $this->redirectToRoute('app_demande_analyse_index');
        }

        $form = $this->createForm(LaboratoireType::class, $laboratoire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($laboratoire->getLaboratoireTypeAnalyses() as $typeAnalyse) {
                $typeAnalyse->setLaboratoire($laboratoire);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Laboratoire mis a jour avec succes.');
            return $this->redirectToRoute('app_demande_analyse_index');
        }

        return $this->render('laboratoire/new.html.twig', [
            'form' => $form->createView(),
            'laboratoire' => $laboratoire,
            'is_edit' => true,
            'return_path' => $this->generateUrl('app_demande_analyse_index'),
        ]);
    }

    #[Route('/demandes/{id}', name: 'app_responsable_labo_demande_edit', methods: ['GET', 'POST'])]
    public function editDemande(
        Request $request,
        DemandeAnalyse $demandeAnalyse,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        MailerInterface $mailer
    ): Response {
        $responsable = $this->getUser();
        if (!$responsable instanceof ResponsableLaboratoire) {
            throw new AccessDeniedException('Acces reserve au responsable laboratoire.');
        }

        $laboratoire = $responsable->getLaboratoire();
        if (!$laboratoire || $demandeAnalyse->getLaboratoire() !== $laboratoire) {
            throw new AccessDeniedException('Acces non autorise a cette demande.');
        }

        if ($request->isMethod('POST')) {
            $statusBeforeUpdate = (string) $demandeAnalyse->getStatut();
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('resp-labo-update' . $demandeAnalyse->getId(), $submittedToken)) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_responsable_labo_demande_edit', ['id' => $demandeAnalyse->getId()]);
            }

            $statut = $request->request->get('statut');
            $statutsValides = ['en_attente', 'envoye'];
            if ($statut && in_array($statut, $statutsValides, true)) {
                $demandeAnalyse->setStatut($statut);
            }

            $resultatFile = $request->files->get('resultat_pdf');
            $shouldSendEmail = false;
            $shouldTriggerAiAnalysis = false;

            if ($resultatFile instanceof UploadedFile) {
                $mimeType = $resultatFile->getMimeType();
                $extension = strtolower((string) $resultatFile->guessExtension());
                if ($mimeType !== 'application/pdf' && $extension !== 'pdf') {
                    $this->addFlash('error', 'Le fichier doit etre un PDF.');
                    return $this->redirectToRoute('app_responsable_labo_demande_edit', ['id' => $demandeAnalyse->getId()]);
                }

                $originalFilename = pathinfo($resultatFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.pdf';

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/resultats';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $resultatFile->move($uploadDir, $newFilename);
                $demandeAnalyse->setResultatPdf('uploads/resultats/' . $newFilename);
                $this->markResultatAnalysePending($demandeAnalyse);
                $shouldSendEmail = true;
                $shouldTriggerAiAnalysis = true;

                if ($demandeAnalyse->getStatut() !== 'envoye') {
                    $demandeAnalyse->setStatut('envoye');
                }
                if (!$demandeAnalyse->getEnvoyeLe()) {
                    $demandeAnalyse->setEnvoyeLe(new \DateTime());
                }
            }

            $demandeAnalyse->setStatut($demandeAnalyse->getResultatPdf() ? 'envoye' : 'en_attente');

            $entityManager->flush();

            $sentNow = $statusBeforeUpdate !== 'envoye' && $demandeAnalyse->getStatut() === 'envoye';
            if ($shouldSendEmail || $sentNow) {
                $this->sendResultEmail($demandeAnalyse, $mailer, $laboratoire->getEmail());
                $this->sendDoctorResultEmail($demandeAnalyse, $mailer, $laboratoire->getEmail());
            }

            $this->addFlash('success', 'Demande mise a jour avec succes.');
            if ($shouldTriggerAiAnalysis) {
                $this->addFlash('info', 'Le PDF est enregistre. Analyse IA en cours en arriere-plan.');
            }

            $redirectParams = ['id' => $demandeAnalyse->getId()];
            if ($shouldTriggerAiAnalysis) {
                $redirectParams['run_ai'] = 1;
            }

            return $this->redirectToRoute('app_responsable_labo_demande_edit', $redirectParams);
        }

        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse();
        $autoRetryFailedAi = (bool) $demandeAnalyse->getResultatPdf()
            && $resultatAnalyse
            && $resultatAnalyse->getAiStatus() === ResultatAnalyse::AI_STATUS_FAILED;
        $autoRetryMissingGlossary = (bool) $demandeAnalyse->getResultatPdf()
            && $resultatAnalyse
            && $resultatAnalyse->getAiStatus() === ResultatAnalyse::AI_STATUS_DONE
            && !$this->hasMetricGlossary($resultatAnalyse);
        $shouldTriggerAiAnalysis = (
                $request->query->getBoolean('run_ai')
                || $autoRetryFailedAi
                || $autoRetryMissingGlossary
            )
            && (bool) $demandeAnalyse->getResultatPdf()
            && (
                !$resultatAnalyse
                || $resultatAnalyse->getAiStatus() !== ResultatAnalyse::AI_STATUS_DONE
                || $autoRetryMissingGlossary
            );

        return $this->render('responsable_labo/demande_edit.html.twig', [
            'demande' => $demandeAnalyse,
            'laboratoire' => $laboratoire,
            'trigger_ai_analysis' => $shouldTriggerAiAnalysis,
        ]);
    }

    #[Route('/demandes/{id}/analyse-ia', name: 'app_responsable_labo_demande_analyse_ia', methods: ['POST'])]
    public function analyseIaDemande(
        Request $request,
        DemandeAnalyse $demandeAnalyse,
        EntityManagerInterface $entityManager,
        FastApiLabAiClient $fastApiLabAiClient,
        MailerInterface $mailer
    ): JsonResponse {
        $responsable = $this->getUser();
        if (!$responsable instanceof ResponsableLaboratoire) {
            throw new AccessDeniedException('Acces reserve au responsable laboratoire.');
        }

        $laboratoire = $responsable->getLaboratoire();
        if (!$laboratoire || $demandeAnalyse->getLaboratoire() !== $laboratoire) {
            throw new AccessDeniedException('Acces non autorise a cette demande.');
        }

        $submittedToken = (string) ($request->request->get('_token') ?: $request->headers->get('X-CSRF-TOKEN', ''));
        if (!$this->isCsrfTokenValid('resp-labo-update' . $demandeAnalyse->getId(), $submittedToken)) {
            return $this->json([
                'ok' => false,
                'message' => 'Token CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $resultatPdf = (string) ($demandeAnalyse->getResultatPdf() ?? '');
        if ($resultatPdf === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Aucun PDF resultat a analyser.',
            ], Response::HTTP_CONFLICT);
        }

        // Continue processing even if user leaves the page while the background call is running.
        ignore_user_abort(true);
        @set_time_limit(0);

        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse();
        $alreadyDoneBeforeRun = $resultatAnalyse
            && $resultatAnalyse->getAiStatus() === ResultatAnalyse::AI_STATUS_DONE;
        if (
            $resultatAnalyse
            && $resultatAnalyse->getAiStatus() === ResultatAnalyse::AI_STATUS_DONE
            && $resultatAnalyse->getSourcePdf() === $resultatPdf
            && $this->hasMetricGlossary($resultatAnalyse)
        ) {
            return $this->json([
                'ok' => true,
                'ai_status' => ResultatAnalyse::AI_STATUS_DONE,
                'message' => 'Analyse IA deja disponible.',
            ]);
        }

        $fullFilePath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($resultatPdf, '/');
        if (!is_file($fullFilePath)) {
            return $this->json([
                'ok' => false,
                'message' => 'Le fichier PDF resultat est introuvable sur le serveur.',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->markResultatAnalysePending($demandeAnalyse);
        $filename = basename($fullFilePath) ?: ('resultat-' . $demandeAnalyse->getId() . '.pdf');
        $this->analyzeAndAttachResultat(
            $demandeAnalyse,
            $fullFilePath,
            $filename,
            $fastApiLabAiClient,
            false
        );
        $entityManager->flush();

        $status = $demandeAnalyse->getResultatAnalyse()?->getAiStatus() ?? ResultatAnalyse::AI_STATUS_PENDING;
        $message = 'Analyse IA indisponible pour ce document.';
        if ($status === ResultatAnalyse::AI_STATUS_DONE) {
            $message = 'Analyse IA terminee.';
            if (!$alreadyDoneBeforeRun) {
                $this->sendDoctorResultEmail($demandeAnalyse, $mailer, $laboratoire->getEmail());
            }
        } elseif ($status === ResultatAnalyse::AI_STATUS_PENDING) {
            $message = 'Analyse IA en attente (service IA temporairement indisponible, reessayez dans quelques instants).';
        }

        return $this->json([
            'ok' => true,
            'ai_status' => $status,
            'message' => $message,
        ]);
    }

    private function markResultatAnalysePending(DemandeAnalyse $demandeAnalyse): void
    {
        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse() ?? new ResultatAnalyse();
        $resultatAnalyse->setDemandeAnalyse($demandeAnalyse);
        $resultatAnalyse->setSourcePdf($demandeAnalyse->getResultatPdf());
        $resultatAnalyse->setAiStatus(ResultatAnalyse::AI_STATUS_PENDING);
        $resultatAnalyse->setAnomalies(null);
        $resultatAnalyse->setDangerScore(null);
        $resultatAnalyse->setDangerLevel(null);
        $resultatAnalyse->setResumeBilan(null);
        $resultatAnalyse->setModeleVersion(null);
        $resultatAnalyse->setAiRawResponse(null);
        $resultatAnalyse->setAnalyseLe(null);
        $resultatAnalyse->touch();
        $demandeAnalyse->setResultatAnalyse($resultatAnalyse);
    }

    private function sendResultEmail(
        DemandeAnalyse $demandeAnalyse,
        MailerInterface $mailer,
        ?string $fromEmail
    ): void
    {
        $patientEmail = $demandeAnalyse->getPatient()?->getEmail();
        if (!$patientEmail || !$demandeAnalyse->getResultatPdf()) {
            return;
        }

        $patientName = $demandeAnalyse->getPatient()?->getNomComplet() ?: 'Patient';
        $laboratoireName = $demandeAnalyse->getLaboratoire()?->getNom() ?: 'Laboratoire';
        $typeBilan = $demandeAnalyse->getTypeBilan() ?: 'Non precise';
        $dateDemande = $demandeAnalyse->getDateDemande()?->format('d/m/Y H:i') ?: '-';
        $mesDemandesUrl = $this->generateUrl('app_demande_analyse_mes_demandes', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $safePatientName = htmlspecialchars($patientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLaboratoireName = htmlspecialchars($laboratoireName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTypeBilan = htmlspecialchars($typeBilan, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeDateDemande = htmlspecialchars($dateDemande, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMesDemandesUrl = htmlspecialchars($mesDemandesUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $textBody = "Bonjour " . $patientName . ",\n\n"
            . "Votre resultat d'analyse est disponible pour la demande #" . $demandeAnalyse->getId() . ".\n\n"
            . "Laboratoire : " . $laboratoireName . "\n"
            . "Type de bilan : " . $typeBilan . "\n"
            . "Date de la demande : " . $dateDemande . "\n\n"
            . "Consultez votre espace patient (Mes demandes):\n"
            . $mesDemandesUrl . "\n\n"
            . "Cordialement,\n"
            . $laboratoireName;

        $htmlBody = <<<HTML
<div style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:#2563eb;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
            Notification resultat disponible
        </div>
        <div style="padding:24px;line-height:1.55;">
            <p style="margin:0 0 12px 0;">Bonjour {$safePatientName},</p>
            <p style="margin:0 0 16px 0;">Votre resultat d'analyse est disponible pour la demande <strong>#{$demandeAnalyse->getId()}</strong>.</p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;margin:0 0 16px 0;">
                <p style="margin:0 0 6px 0;"><strong>Laboratoire :</strong> {$safeLaboratoireName}</p>
                <p style="margin:0 0 6px 0;"><strong>Type de bilan :</strong> {$safeTypeBilan}</p>
                <p style="margin:0;"><strong>Date de la demande :</strong> {$safeDateDemande}</p>
            </div>

            <p style="margin:0 0 12px 0;">Pour consulter votre resultat, allez dans votre espace patient.</p>
            <p style="margin:0 0 20px 0;">
                <a href="{$safeMesDemandesUrl}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">
                    Ouvrir Mes Demandes
                </a>
            </p>
            <p style="margin:0;">Cordialement,<br><strong>{$safeLaboratoireName}</strong></p>
        </div>
    </div>
</div>
HTML;

        $from = $fromEmail ?: 'no-reply@sahty.local';
        $email = (new Email())
            ->from($from)
            ->to($patientEmail)
            ->subject('Resultat disponible - Demande #' . $demandeAnalyse->getId())
            ->text($textBody)
            ->html($htmlBody);

        $mailer->send($email);
    }

    private function sendDoctorResultEmail(
        DemandeAnalyse $demandeAnalyse,
        MailerInterface $mailer,
        ?string $fromEmail
    ): void {
        $doctorEmail = $demandeAnalyse->getMedecin()?->getEmail();
        if (!$doctorEmail || !$demandeAnalyse->getResultatPdf()) {
            return;
        }

        $pdfPublicPath = (string) $demandeAnalyse->getResultatPdf();
        $pdfAbsolutePath = $this->getParameter('kernel.project_dir') . '/public/' . $pdfPublicPath;
        $hasPdfAttachment = is_file($pdfAbsolutePath);
        $pdfAttachmentName = basename($pdfPublicPath) ?: 'resultat-analyse.pdf';

        $doctorName = $demandeAnalyse->getMedecin()?->getNomComplet() ?: 'Docteur';
        $patientName = $demandeAnalyse->getPatient()?->getNomComplet() ?: 'Patient';
        $laboratoireName = $demandeAnalyse->getLaboratoire()?->getNom() ?: 'Laboratoire';
        $typeBilan = $demandeAnalyse->getTypeBilan() ?: 'Non precise';
        $dateDemande = $demandeAnalyse->getDateDemande()?->format('d/m/Y H:i') ?: '-';
        $resultatUrl = $this->generateUrl('app_demande_analyse_mes_demandes', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $safeDoctorName = htmlspecialchars($doctorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safePatientName = htmlspecialchars($patientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLaboratoireName = htmlspecialchars($laboratoireName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTypeBilan = htmlspecialchars($typeBilan, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeDateDemande = htmlspecialchars($dateDemande, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeResultatUrl = htmlspecialchars($resultatUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse();
        $raw = is_array($resultatAnalyse?->getAiRawResponse()) ? $resultatAnalyse->getAiRawResponse() : [];
        $llmInterpretation = is_array($raw['llm_interpretation'] ?? null) ? $raw['llm_interpretation'] : [];
        $anomalies = $resultatAnalyse?->getAnomalies();
        if (!is_array($anomalies)) {
            $anomalies = [];
        }

        $dangerLevel = $resultatAnalyse?->getDangerLevel() ?: '-';
        $dangerScore = $resultatAnalyse?->getDangerScore();
        $dangerText = $dangerScore !== null ? sprintf('%s (%d/100)', $dangerLevel, $dangerScore) : $dangerLevel;
        $aiStatus = $resultatAnalyse?->getAiStatus() ?? ResultatAnalyse::AI_STATUS_PENDING;
        $model = $resultatAnalyse?->getModeleVersion() ?: '-';
        $resume = trim((string) ($llmInterpretation['clinician_summary'] ?? $resultatAnalyse?->getResumeBilan() ?? ''));
        if ($resume === '') {
            $resume = 'Resume IA non disponible.';
        }
        $urgency = trim((string) ($llmInterpretation['urgency'] ?? ''));
        $urgencyReason = trim((string) ($llmInterpretation['urgency_reason'] ?? ''));
        $actions = $this->normalizeAiList($llmInterpretation['suggested_actions'] ?? null);

        $textBody = "Bonjour Dr. " . $doctorName . ",\n\n"
            . "Un nouveau resultat de bilan est disponible.\n\n"
            . "Demande : #" . $demandeAnalyse->getId() . "\n"
            . "Patient : " . $patientName . "\n"
            . "Laboratoire : " . $laboratoireName . "\n"
            . "Type de bilan : " . $typeBilan . "\n"
            . "Date de la demande : " . $dateDemande . "\n\n"
            . "--- Synthese IA ---\n"
            . "Statut IA : " . $aiStatus . "\n"
            . "Niveau danger : " . $dangerText . "\n"
            . "Modele : " . $model . "\n"
            . "Resume clinicien : " . $resume . "\n";

        if ($urgency !== '') {
            $textBody .= "Urgence : " . $urgency . ($urgencyReason !== '' ? ' - ' . $urgencyReason : '') . "\n";
        }
        if ($actions) {
            $textBody .= "Actions suggerees:\n";
            foreach ($actions as $action) {
                $textBody .= "- " . $action . "\n";
            }
        }

        $textBody .= "\n--- Anomalies ---\n" . $this->formatAnomaliesText($anomalies) . "\n\n"
            . ($hasPdfAttachment ? "Le PDF resultat est joint a ce message.\n" : "PDF resultat introuvable en piece jointe.\n")
            . "Lien plateforme patient : " . $resultatUrl . "\n\n"
            . "Cordialement,\n"
            . $laboratoireName;

        $safeAiStatus = htmlspecialchars($aiStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeDangerText = htmlspecialchars($dangerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeModel = htmlspecialchars($model, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeResume = nl2br(htmlspecialchars($resume, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $safeUrgency = htmlspecialchars($urgency, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrgencyReason = htmlspecialchars($urgencyReason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $actionsHtml = '';
        if ($actions) {
            $items = array_map(
                static fn (string $a): string => '<li>' . htmlspecialchars($a, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
                $actions
            );
            $actionsHtml = '<p style="margin:0 0 8px 0;"><strong>Actions suggerees :</strong></p><ul style="margin:0 0 10px 18px;padding:0;">' . implode('', $items) . '</ul>';
        }

        $urgencyHtml = '';
        if ($urgency !== '') {
            $urgencyHtml = '<p style="margin:0 0 6px 0;"><strong>Urgence :</strong> ' . $safeUrgency
                . ($urgencyReason !== '' ? ' - ' . $safeUrgencyReason : '') . '</p>';
        }

        $anomalyTableRows = $this->buildAnomalyTableRowsHtml($anomalies);
        $pdfAttachmentHintHtml = htmlspecialchars(
            $this->buildDoctorPdfAttachmentHintHtml($hasPdfAttachment),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $htmlBody = <<<HTML
<div style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:760px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:#1d4ed8;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
            Resultat de bilan disponible - Synthese medecin
        </div>
        <div style="padding:24px;line-height:1.55;">
            <p style="margin:0 0 12px 0;">Bonjour Dr. {$safeDoctorName},</p>
            <p style="margin:0 0 14px 0;">Le resultat de la demande <strong>#{$demandeAnalyse->getId()}</strong> est maintenant disponible.</p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;margin:0 0 14px 0;">
                <p style="margin:0 0 6px 0;"><strong>Patient :</strong> {$safePatientName}</p>
                <p style="margin:0 0 6px 0;"><strong>Laboratoire :</strong> {$safeLaboratoireName}</p>
                <p style="margin:0 0 6px 0;"><strong>Type de bilan :</strong> {$safeTypeBilan}</p>
                <p style="margin:0;"><strong>Date de la demande :</strong> {$safeDateDemande}</p>
            </div>

            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin:0 0 14px 0;">
                <p style="margin:0 0 6px 0;"><strong>Statut IA :</strong> {$safeAiStatus}</p>
                <p style="margin:0 0 6px 0;"><strong>Niveau danger :</strong> {$safeDangerText}</p>
                <p style="margin:0 0 6px 0;"><strong>Modele :</strong> {$safeModel}</p>
                {$urgencyHtml}
                <p style="margin:0 0 8px 0;"><strong>Resume clinicien :</strong></p>
                <p style="margin:0 0 10px 0;">{$safeResume}</p>
                {$actionsHtml}
            </div>

            <h3 style="margin:0 0 10px 0;color:#0f172a;font-size:18px;">Anomalies</h3>
            <table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #d1d5db;margin-bottom:14px;">
                <thead>
                    <tr style="background:#e5edf7;">
                        <th style="border:1px solid #d1d5db;padding:8px;text-align:left;">Name</th>
                        <th style="border:1px solid #d1d5db;padding:8px;text-align:left;">Value</th>
                        <th style="border:1px solid #d1d5db;padding:8px;text-align:left;">Reference</th>
                        <th style="border:1px solid #d1d5db;padding:8px;text-align:left;">Status</th>
                        <th style="border:1px solid #d1d5db;padding:8px;text-align:left;">Severity</th>
                        <th style="border:1px solid #d1d5db;padding:8px;text-align:left;">Note</th>
                    </tr>
                </thead>
                <tbody>
                    {$anomalyTableRows}
                </tbody>
            </table>

            <p style="margin:0 0 10px 0;">
                <a href="{$safeResultatUrl}" style="display:inline-block;background:#1d4ed8;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">
                    Ouvrir la plateforme
                </a>
            </p>
            <p style="margin:0 0 10px 0;color:#334155;">{$pdfAttachmentHintHtml}</p>
            <p style="margin:0;">Cordialement,<br><strong>{$safeLaboratoireName}</strong></p>
        </div>
    </div>
</div>
HTML;

        $from = $fromEmail ?: 'no-reply@sahty.local';
        $email = (new Email())
            ->from($from)
            ->to($doctorEmail)
            ->subject('Synthese IA bilan patient - Demande #' . $demandeAnalyse->getId())
            ->text($textBody)
            ->html($htmlBody);

        if ($hasPdfAttachment) {
            $email->attachFromPath($pdfAbsolutePath, $pdfAttachmentName, 'application/pdf');
        }

        $mailer->send($email);
    }

    private function analyzeAndAttachResultat(
        DemandeAnalyse $demandeAnalyse,
        string $fullPdfPath,
        string $filename,
        FastApiLabAiClient $fastApiLabAiClient,
        bool $addFlashOnFailure = true
    ): void {
        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse() ?? new ResultatAnalyse();
        $resultatAnalyse->setDemandeAnalyse($demandeAnalyse);
        $resultatAnalyse->setSourcePdf($demandeAnalyse->getResultatPdf());
        $resultatAnalyse->setAiStatus(ResultatAnalyse::AI_STATUS_PENDING);
        $resultatAnalyse->touch();

        try {
            $payload = $fastApiLabAiClient->analyzePdf($fullPdfPath, $filename);

            $analysis = is_array($payload['analysis'] ?? null) ? $payload['analysis'] : [];
            $llmInterpretation = is_array($payload['llm_interpretation'] ?? null) ? $payload['llm_interpretation'] : [];

            $dangerScore = isset($analysis['danger_score']) ? (int) $analysis['danger_score'] : null;
            $dangerLevel = isset($analysis['danger_level']) ? (string) $analysis['danger_level'] : null;
            $summary = isset($analysis['summary']) ? (string) $analysis['summary'] : '';
            $clinicianSummary = isset($llmInterpretation['clinician_summary']) ? (string) $llmInterpretation['clinician_summary'] : '';
            $urgency = isset($llmInterpretation['urgency']) ? (string) $llmInterpretation['urgency'] : '';
            $urgencyReason = isset($llmInterpretation['urgency_reason']) ? (string) $llmInterpretation['urgency_reason'] : '';
            $model = isset($analysis['model']) ? (string) $analysis['model'] : null;
            $llmModel = isset($llmInterpretation['model']) ? (string) $llmInterpretation['model'] : null;

            $resumeParts = [];
            if ($summary !== '') {
                $resumeParts[] = 'Analyse: ' . $summary;
            }
            if ($clinicianSummary !== '') {
                $resumeParts[] = 'Interpretation clinicien: ' . $clinicianSummary;
            }
            if ($urgency !== '') {
                $resumeParts[] = 'Urgence: ' . $urgency . ($urgencyReason !== '' ? ' - ' . $urgencyReason : '');
            }

            $modelVersion = trim(implode(' | ', array_filter([$model, $llmModel], static fn ($v) => $v !== null && $v !== '')));
            $anomalies = is_array($analysis['anomalies'] ?? null) ? $analysis['anomalies'] : null;

            $resultatAnalyse->setAiStatus(ResultatAnalyse::AI_STATUS_DONE);
            $resultatAnalyse->setDangerScore($dangerScore);
            $resultatAnalyse->setDangerLevel($dangerLevel);
            $resultatAnalyse->setResumeBilan($resumeParts ? implode("\n\n", $resumeParts) : null);
            $resultatAnalyse->setModeleVersion($modelVersion !== '' ? $modelVersion : null);
            $resultatAnalyse->setAnomalies($anomalies);
            $resultatAnalyse->setAiRawResponse($payload);
            $resultatAnalyse->setAnalyseLe(new \DateTime());
            $resultatAnalyse->touch();
        } catch (\Throwable $e) {
            $errorMessage = trim((string) $e->getMessage());
            if ($this->isTransientAiFailure($errorMessage)) {
                $resultatAnalyse->setAiStatus(ResultatAnalyse::AI_STATUS_PENDING);
                $resultatAnalyse->setResumeBilan('Analyse IA en attente: service IA temporairement indisponible.');
                $resultatAnalyse->setAiRawResponse([
                    'error' => $errorMessage,
                    'transient' => true,
                ]);
                $resultatAnalyse->setAnalyseLe(new \DateTime());
                $resultatAnalyse->touch();

                if ($addFlashOnFailure) {
                    $this->addFlash('info', 'Le resultat PDF est enregistre. L analyse IA est en attente (timeout service IA).');
                }
            } else {
                $resultatAnalyse->setAiStatus(ResultatAnalyse::AI_STATUS_FAILED);
                $resultatAnalyse->setResumeBilan('Analyse IA indisponible: ' . $errorMessage);
                $resultatAnalyse->setAiRawResponse([
                    'error' => $errorMessage,
                ]);
                $resultatAnalyse->setAnalyseLe(new \DateTime());
                $resultatAnalyse->touch();

                if ($addFlashOnFailure) {
                    $this->addFlash('warning', 'Le resultat PDF est enregistre, mais l\'analyse IA distante a echoue.');
                }
            }

        }

        $demandeAnalyse->setResultatAnalyse($resultatAnalyse);
    }

    private function isTransientAiFailure(string $message): bool
    {
        $m = strtolower($message);
        return str_contains($m, 'timeout')
            || str_contains($m, 'timed out')
            || str_contains($m, 'idle timeout')
            || str_contains($m, 'temporarily')
            || str_contains($m, 'temporary')
            || str_contains($m, 'echec de connexion');
    }

    private function hasMetricGlossary(ResultatAnalyse $resultatAnalyse): bool
    {
        $raw = $resultatAnalyse->getAiRawResponse();
        if (!is_array($raw)) {
            return false;
        }

        $sources = [
            $raw['metric_glossary'] ?? null,
            $raw['analysis']['metric_glossary'] ?? null,
            $raw['glossary'] ?? null,
        ];

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return true;
                }
                if (is_array($value)) {
                    $description = $value['description'] ?? $value['text'] ?? $value['summary'] ?? null;
                    if (is_scalar($description) && trim((string) $description) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<int,mixed>|null $anomalies
     */
    private function formatTopAnomalies(?array $anomalies): string
    {
        if (!$anomalies) {
            return '';
        }

        $lines = [];
        foreach ($anomalies as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = isset($item['name']) ? (string) $item['name'] : '';
            if ($name === '') {
                continue;
            }
            $value = isset($item['value']) ? (string) $item['value'] : '';
            $status = isset($item['status']) ? (string) $item['status'] : '';
            $ref = isset($item['reference']) ? (string) $item['reference'] : '';

            $line = '- ' . $name;
            if ($value !== '') {
                $line .= ' = ' . $value;
            }
            if ($status !== '') {
                $line .= ' (' . $status . ')';
            }
            if ($ref !== '') {
                $line .= ' [ref: ' . $ref . ']';
            }
            $lines[] = $line;
            if (count($lines) >= 5) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param mixed $source
     * @return array<int,string>
     */
    private function normalizeAiList(mixed $source): array
    {
        if (is_array($source)) {
            $lines = [];
            foreach ($source as $item) {
                if (is_scalar($item)) {
                    $value = trim((string) $item);
                    if ($value !== '') {
                        $lines[] = $value;
                    }
                }
            }
            return array_values(array_unique($lines));
        }

        if (!is_string($source)) {
            return [];
        }

        $parts = preg_split('/[\r\n;]+/', $source) ?: [];
        $lines = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            $value = ltrim($value, "-* \t");
            if ($value !== '') {
                $lines[] = $value;
            }
        }

        return array_values(array_unique($lines));
    }

    /**
     * @param array<int,mixed> $anomalies
     */
    private function buildAnomalyTableRowsHtml(array $anomalies): string
    {
        if (!$anomalies) {
            return '<tr><td colspan="6" style="border:1px solid #d1d5db;padding:8px;color:#6b7280;">Aucune anomalie structuree disponible.</td></tr>';
        }

        $rows = [];
        foreach ($anomalies as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = htmlspecialchars((string) ($item['name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = htmlspecialchars((string) ($item['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $reference = htmlspecialchars((string) ($item['reference'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $status = htmlspecialchars((string) ($item['status'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $severity = htmlspecialchars((string) ($item['severity'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $note = htmlspecialchars((string) ($item['note'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $rows[] = '<tr>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $name . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $value . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $reference . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $status . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $severity . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:8px;">' . $note . '</td>'
                . '</tr>';
        }

        if (!$rows) {
            return '<tr><td colspan="6" style="border:1px solid #d1d5db;padding:8px;color:#6b7280;">Aucune anomalie structuree disponible.</td></tr>';
        }

        return implode('', $rows);
    }

    /**
     * @param array<int,mixed> $anomalies
     */
    private function formatAnomaliesText(array $anomalies): string
    {
        if (!$anomalies) {
            return 'Aucune anomalie structuree disponible.';
        }

        $lines = [];
        foreach ($anomalies as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $value = trim((string) ($item['value'] ?? '-'));
            $reference = trim((string) ($item['reference'] ?? '-'));
            $status = trim((string) ($item['status'] ?? '-'));
            $severity = trim((string) ($item['severity'] ?? '-'));
            $note = trim((string) ($item['note'] ?? '-'));

            $lines[] = sprintf(
                '- %s | value: %s | ref: %s | status: %s | severity: %s | note: %s',
                $name,
                $value !== '' ? $value : '-',
                $reference !== '' ? $reference : '-',
                $status !== '' ? $status : '-',
                $severity !== '' ? $severity : '-',
                $note !== '' ? $note : '-'
            );
        }

        return $lines ? implode("\n", $lines) : 'Aucune anomalie structuree disponible.';
    }

    private function buildDoctorPdfAttachmentHintHtml(bool $hasPdfAttachment): string
    {
        if ($hasPdfAttachment) {
            return 'Le PDF resultat est joint a ce message.';
        }

        return 'Le PDF resultat n\'a pas pu etre joint automatiquement.';
    }

    private function buildDemandesViewData(
        Request $request,
        DemandeAnalyseRepository $demandeAnalyseRepository,
        EntityManagerInterface $entityManager
    ): array {
        $responsable = $this->getUser();
        if (!$responsable instanceof ResponsableLaboratoire) {
            throw new AccessDeniedException('Acces reserve au responsable laboratoire.');
        }

        $laboratoire = $responsable->getLaboratoire();
        if (!$laboratoire) {
            return [null, [], [], [
                'statut' => '',
                'type_bilan' => '',
                'priorite' => '',
                'date' => '',
                'sort' => 'date',
                'dir' => 'desc',
            ], [
                'total' => 0,
                'en_attente' => 0,
                'envoye' => 0,
            ], [
                'page' => 1,
                'per_page' => 6,
                'total' => 0,
                'total_pages' => 1,
            ]];
        }

        $allDemandes = $demandeAnalyseRepository->findBy(
            ['laboratoire' => $laboratoire],
            ['date_demande' => 'DESC']
        );

        $statutFilter = trim((string) $request->query->get('statut', ''));
        $typeBilanFilter = trim((string) $request->query->get('type_bilan', ''));
        $prioriteFilter = trim((string) $request->query->get('priorite', ''));
        $dateFilter = trim((string) $request->query->get('date', ''));
        $sort = trim((string) $request->query->get('sort', 'date'));
        $dir = strtolower(trim((string) $request->query->get('dir', 'desc')));
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'desc';
        }

        $demandes = array_values(array_filter(
            $allDemandes,
            static function (DemandeAnalyse $demande) use ($statutFilter, $typeBilanFilter, $prioriteFilter, $dateFilter): bool {
                $effectiveStatut = $demande->getResultatPdf() ? 'envoye' : 'en_attente';
                if ($statutFilter !== '' && $effectiveStatut !== $statutFilter) {
                    return false;
                }
                if ($typeBilanFilter !== '' && $demande->getTypeBilan() !== $typeBilanFilter) {
                    return false;
                }
                if ($prioriteFilter !== '' && $demande->getPriorite() !== $prioriteFilter) {
                    return false;
                }
                if ($dateFilter !== '') {
                    $dateProgramme = $demande->getProgrammeLe()?->format('Y-m-d');
                    if ($dateProgramme !== $dateFilter) {
                        return false;
                    }
                }
                return true;
            }
        ));

        $sortField = $sort;
        $sortDir = $dir;
        usort($demandes, static function (DemandeAnalyse $a, DemandeAnalyse $b) use ($sortField, $sortDir): int {
            $valueA = '';
            $valueB = '';

            switch ($sortField) {
                case 'patient':
                    $valueA = $a->getPatient()?->getNomComplet() ?? '';
                    $valueB = $b->getPatient()?->getNomComplet() ?? '';
                    break;
                case 'medecin':
                    $valueA = $a->getMedecin()?->getNomComplet() ?? '';
                    $valueB = $b->getMedecin()?->getNomComplet() ?? '';
                    break;
                case 'type_bilan':
                    $valueA = $a->getTypeBilan() ?? '';
                    $valueB = $b->getTypeBilan() ?? '';
                    break;
                case 'statut':
                    $valueA = $a->getResultatPdf() ? 'envoye' : 'en_attente';
                    $valueB = $b->getResultatPdf() ? 'envoye' : 'en_attente';
                    break;
                case 'resultat':
                    $valueA = $a->getResultatPdf() ? '1' : '0';
                    $valueB = $b->getResultatPdf() ? '1' : '0';
                    break;
                case 'date':
                default:
                    $valueA = $a->getDateDemande()->getTimestamp();
                    $valueB = $b->getDateDemande()->getTimestamp();
                    break;
            }

            if (is_int($valueA) || is_int($valueB)) {
                $result = $valueA <=> $valueB;
            } else {
                $result = strcasecmp((string) $valueA, (string) $valueB);
            }

            return $sortDir === 'asc' ? $result : -$result;
        });

        $typeBilanOptions = [];
        foreach ($allDemandes as $demande) {
            $type = $demande->getTypeBilan();
            if ($type) {
                $typeBilanOptions[$type] = $type;
            }
        }
        ksort($typeBilanOptions);

        $totalFiltered = count($demandes);
        $stats = [
            'total' => $totalFiltered,
            'en_attente' => 0,
            'envoye' => 0,
        ];
        foreach ($demandes as $demande) {
            $statut = $demande->getResultatPdf() ? 'envoye' : 'en_attente';
            $stats[$statut]++;
        }

        $perPage = 6;
        $page = (int) $request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $totalPages = (int) max(1, (int) ceil($totalFiltered / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $demandes = array_slice($demandes, $offset, $perPage);

        return [
            $laboratoire,
            $demandes,
            $typeBilanOptions,
            [
                'statut' => $statutFilter,
                'type_bilan' => $typeBilanFilter,
                'priorite' => $prioriteFilter,
                'date' => $dateFilter,
                'sort' => $sort,
                'dir' => $dir,
            ],
            $stats,
            [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $totalFiltered,
                'total_pages' => $totalPages,
            ],
        ];
    }

}
