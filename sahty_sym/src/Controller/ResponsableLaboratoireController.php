<?php

namespace App\Controller;

use App\Entity\DemandeAnalyse;
use App\Entity\ResponsableLaboratoire;
use App\Form\LaboratoireType;
use App\Repository\DemandeAnalyseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
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
                $shouldSendEmail = true;

                if ($demandeAnalyse->getStatut() !== 'envoye') {
                    $demandeAnalyse->setStatut('envoye');
                }
                if (!$demandeAnalyse->getEnvoyeLe()) {
                    $demandeAnalyse->setEnvoyeLe(new \DateTime());
                }
            }

            $demandeAnalyse->setStatut($demandeAnalyse->getResultatPdf() ? 'envoye' : 'en_attente');

            $entityManager->flush();

            if ($shouldSendEmail) {
                $this->sendResultEmail($demandeAnalyse, $mailer, $laboratoire->getEmail());
            }

            $this->addFlash('success', 'Demande mise a jour avec succes.');
            return $this->redirectToRoute('app_responsable_labo_demande_edit', ['id' => $demandeAnalyse->getId()]);
        }

        return $this->render('responsable_labo/demande_edit.html.twig', [
            'demande' => $demandeAnalyse,
            'laboratoire' => $laboratoire,
        ]);
    }

    private function sendResultEmail(DemandeAnalyse $demandeAnalyse, MailerInterface $mailer, ?string $fromEmail): void
    {
        $recipients = [];
        $patientEmail = $demandeAnalyse->getPatient()?->getEmail();
        $medecinEmail = $demandeAnalyse->getMedecin()?->getEmail();

        if ($patientEmail) {
            $recipients[] = $patientEmail;
        }
        if ($medecinEmail) {
            $recipients[] = $medecinEmail;
        }
        $recipients = array_values(array_unique($recipients));

        if (!$recipients || !$demandeAnalyse->getResultatPdf()) {
            return;
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $demandeAnalyse->getResultatPdf();
        if (!is_file($filePath)) {
            return;
        }

                $patientName = $demandeAnalyse->getPatient()?->getNomComplet() ?: 'Patient';
                $medecinName = $demandeAnalyse->getMedecin()?->getNomComplet() ?: 'Medecin';
                $laboratoireName = $demandeAnalyse->getLaboratoire()?->getNom() ?: 'Laboratoire';
                $typeBilan = $demandeAnalyse->getTypeBilan() ?: 'Non precise';
                $dateDemande = $demandeAnalyse->getDateDemande()?->format('d/m/Y H:i') ?: '-';

                $safePatientName = htmlspecialchars($patientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeMedecinName = htmlspecialchars($medecinName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeLaboratoireName = htmlspecialchars($laboratoireName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeTypeBilan = htmlspecialchars($typeBilan, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeDateDemande = htmlspecialchars($dateDemande, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $textBody = "Bonjour,\n\n"
                        . "Le resultat d'analyse pour la demande #" . $demandeAnalyse->getId() . " est disponible.\n\n"
                        . "Patient : " . $patientName . "\n"
                        . "Medecin : " . $medecinName . "\n"
                        . "Laboratoire : " . $laboratoireName . "\n"
                        . "Type de bilan : " . $typeBilan . "\n"
                        . "Date de la demande : " . $dateDemande . "\n\n"
                        . "Veuillez trouver le PDF en piece jointe.\n\n"
                        . "Cordialement,\n"
                        . $laboratoireName;

                $htmlBody = <<<HTML
<div style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:#2563eb;color:#ffffff;padding:16px 24px;font-size:18px;font-weight:700;">
            Resultat d'analyse disponible
        </div>
        <div style="padding:24px;line-height:1.55;">
            <p style="margin:0 0 12px 0;">Bonjour,</p>
            <p style="margin:0 0 16px 0;">Le resultat d'analyse pour la demande <strong>#{$demandeAnalyse->getId()}</strong> est disponible.</p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;margin:0 0 16px 0;">
                <p style="margin:0 0 6px 0;"><strong>Patient :</strong> {$safePatientName}</p>
                <p style="margin:0 0 6px 0;"><strong>Medecin :</strong> {$safeMedecinName}</p>
                <p style="margin:0 0 6px 0;"><strong>Laboratoire :</strong> {$safeLaboratoireName}</p>
                <p style="margin:0 0 6px 0;"><strong>Type de bilan :</strong> {$safeTypeBilan}</p>
                <p style="margin:0;"><strong>Date de la demande :</strong> {$safeDateDemande}</p>
            </div>

            <p style="margin:0 0 16px 0;">Le document PDF est joint a ce message.</p>
            <p style="margin:0;">Cordialement,<br><strong>{$safeLaboratoireName}</strong></p>
        </div>
    </div>
</div>
HTML;

        $from = $fromEmail ?: 'no-reply@sahty.local';
        $email = (new Email())
            ->from($from)
            ->to(...$recipients)
            ->subject('Resultat d\'analyse - Demande #' . $demandeAnalyse->getId())
                        ->text($textBody)
                        ->html($htmlBody)
            ->attachFromPath($filePath, 'resultat-analyse.pdf', 'application/pdf');

        $mailer->send($email);
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
