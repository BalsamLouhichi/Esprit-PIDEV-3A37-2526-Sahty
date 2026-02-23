<?php
// src/Controller/DemandeAnalyseController.php

namespace App\Controller;

use App\Entity\DemandeAnalyse;
use App\Entity\Patient;
use App\Entity\Laboratoire;
use App\Entity\Medecin;
use App\Entity\ResultatAnalyse;
use App\Entity\ResponsableLaboratoire;
use App\Form\DemandeAnalyseType;
use App\Repository\DemandeAnalyseRepository;
use App\Service\PatientResultQaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/demande-analyse')]
class DemandeAnalyseController extends AbstractController
{
    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Récupère l'utilisateur connecté ou un utilisateur statique par défaut pour les tests
     */
    private function getTestUser(): ?UserInterface
    {
        $user = $this->getUser();
        
        // Si un utilisateur est connecté, on l'utilise
        if ($user instanceof UserInterface) {
            return $user;
        }
        
        // Sinon, on utilise un utilisateur statique pour les tests
        // Essayons d'abord de trouver un patient avec ID 1
        $testPatient = $this->entityManager->getRepository(Patient::class)->find(1);
        if ($testPatient) {
            return $testPatient;
        }
        
        // Si aucun patient ID 1, prenons le premier patient disponible
        $testPatient = $this->entityManager->getRepository(Patient::class)->findOneBy([]);
        if ($testPatient) {
            return $testPatient;
        }
        
        // Si aucun patient, essayons avec un médecin
        $testMedecin = $this->entityManager->getRepository(Medecin::class)->findOneBy([]);
        if ($testMedecin) {
            return $testMedecin;
        }
        
        return null;
    }
    
    /**
     * Vérifie si on est en mode test (pas d'utilisateur connecté)
     */
    private function isTestMode(): bool
    {
        return !$this->getUser();
    }

    /**
     * Liste des demandes d'analyse (pour administrateurs et médecins)
     */
    #[Route('/', name: 'app_demande_analyse_index', methods: ['GET'])]
    public function index(DemandeAnalyseRepository $demandeAnalyseRepository): Response
    {
        $user = $this->getTestUser();
        
        // Mode test: on simule un rôle patient
        if ($this->isTestMode()) {
            // En mode test, on utilise la vue patient
            return $this->redirectToRoute('app_demande_analyse_mes_demandes');
        }

        // Si l'utilisateur est un patient, rediriger vers ses propres demandes
        if ($user instanceof Patient) {
            return $this->redirectToRoute('app_demande_analyse_mes_demandes');
        }

        // Pour les responsables de laboratoire, voir seulement les demandes du labo
        if ($user instanceof ResponsableLaboratoire) {
            // Utiliser la route dediee du responsable labo qui applique
            // la pagination serveur, filtres et tri.
            return $this->redirectToRoute('app_responsable_labo_demandes');
        }

        // Pour les médecins, voir seulement leurs propres demandes
        if ($user instanceof Medecin) {
            $demandes = $demandeAnalyseRepository->findBy(['medecin' => $user]);
        } else {
            // Pour les admins, voir toutes les demandes
            $demandes = $demandeAnalyseRepository->findAll();
        }

        return $this->render('demande_analyse/index.html.twig', [
            'demande_analyses' => $demandes,
            'controller_name' => 'DemandeAnalyseController',
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Liste des demandes du patient connecté
     */
    #[Route('/mes-demandes', name: 'app_demande_analyse_mes_demandes', methods: ['GET'])]
    public function mesDemandes(Request $request, DemandeAnalyseRepository $demandeAnalyseRepository): Response
    {
        [$demandes, $typeBilanOptions, $typeBilanFilter] = $this->buildMesDemandesData($request, $demandeAnalyseRepository);

        return $this->render('demande_analyse/mes_demandes.html.twig', [
            'demandes' => $demandes,
            'type_bilan_filter' => $typeBilanFilter,
            'type_bilan_options' => $typeBilanOptions,
            'test_mode' => $this->isTestMode(),
        ]);
    }

    #[Route('/mes-demandes/filter', name: 'app_demande_analyse_mes_demandes_filter', methods: ['GET'])]
    public function mesDemandesFilter(Request $request, DemandeAnalyseRepository $demandeAnalyseRepository): JsonResponse
    {
        [$demandes, $typeBilanOptions, $typeBilanFilter] = $this->buildMesDemandesData($request, $demandeAnalyseRepository);

        $html = $this->renderView('demande_analyse/_mes_demandes_results.html.twig', [
            'demandes' => $demandes,
        ]);

        $countText = $demandes ? sprintf('%d demande(s) trouvée(s)', count($demandes)) : 'Aucune demande pour le moment';

        return $this->json([
            'html' => $html,
            'count_text' => $countText,
        ]);
    }

    #[Route('/{id}/ia-interpretation', name: 'app_demande_analyse_ia_interpretation', methods: ['GET'])]
    public function iaInterpretation(DemandeAnalyse $demandeAnalyse): JsonResponse
    {
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }

        if (!$demandeAnalyse->getResultatPdf()) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'Le resultat PDF n\'est pas encore disponible.',
            ], 404);
        }

        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse();
        if (!$resultatAnalyse) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'Aucune interpretation IA n\'est disponible pour cette demande.',
            ], 404);
        }

        if ($resultatAnalyse->getAiStatus() !== ResultatAnalyse::AI_STATUS_DONE) {
            $status = $resultatAnalyse->getAiStatus();
            $message = 'Analyse IA en attente.';
            if ($status === ResultatAnalyse::AI_STATUS_FAILED) {
                $message = 'Analyse IA indisponible pour ce document.';
            }

            return $this->json([
                'ok' => false,
                'available' => false,
                'ai_status' => $status,
                'message' => $message,
            ]);
        }

        $raw = $resultatAnalyse->getAiRawResponse();
        if (!is_array($raw)) {
            $raw = [];
        }
        $analysis = is_array($raw['analysis'] ?? null) ? $raw['analysis'] : [];
        $llmInterpretation = is_array($raw['llm_interpretation'] ?? null) ? $raw['llm_interpretation'] : [];
        $metricGlossary = $this->resolveMetricGlossary($analysis, $raw);

        $summary = $this->resolveAiSummary(
            $llmInterpretation,
            $analysis,
            (string) $resultatAnalyse->getResumeBilan()
        );

        $evidenceItems = $this->resolveAiEvidenceItems($resultatAnalyse, $analysis, $llmInterpretation, $raw);

        return $this->json([
            'ok' => true,
            'available' => true,
            'ai_status' => $resultatAnalyse->getAiStatus(),
            'danger_level' => $resultatAnalyse->getDangerLevel(),
            'danger_level_label' => $this->localizeDangerLevel($resultatAnalyse->getDangerLevel()),
            'danger_score' => $resultatAnalyse->getDangerScore(),
            'summary' => $summary,
            'evidence_items' => $evidenceItems,
            'evidence_lines' => array_values(array_map(
                static fn (array $item): string => (string) ($item['line'] ?? ''),
                $evidenceItems
            )),
            'recommendations' => $this->resolveAiRecommendations($analysis, $llmInterpretation, $raw),
            'metric_glossary' => $metricGlossary,
            'model' => $resultatAnalyse->getModeleVersion(),
            'updated_at' => $resultatAnalyse->getUpdatedAt()?->format('d/m/Y H:i'),
        ]);
    }

    #[Route('/{id}/ia-qa', name: 'app_demande_analyse_ia_qa', methods: ['POST'])]
    public function iaQa(
        Request $request,
        DemandeAnalyse $demandeAnalyse,
        PatientResultQaService $patientResultQaService
    ): JsonResponse {
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }

        if (!$demandeAnalyse->getResultatPdf()) {
            return $this->json([
                'ok' => false,
                'message' => 'Le resultat PDF n est pas encore disponible.',
            ], 404);
        }

        $resultatAnalyse = $demandeAnalyse->getResultatAnalyse();
        if (!$resultatAnalyse) {
            return $this->json([
                'ok' => false,
                'message' => 'Aucune interpretation IA n est disponible pour cette demande.',
            ], 404);
        }

        if ($resultatAnalyse->getAiStatus() !== ResultatAnalyse::AI_STATUS_DONE) {
            $status = $resultatAnalyse->getAiStatus();
            $message = 'L analyse IA est encore en attente.';
            if ($status === ResultatAnalyse::AI_STATUS_FAILED) {
                $message = 'L analyse IA est indisponible pour ce document.';
            }

            return $this->json([
                'ok' => false,
                'message' => $message,
                'ai_status' => $status,
            ], 409);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json([
                'ok' => false,
                'message' => 'Format JSON invalide.',
            ], 400);
        }

        $question = trim((string) ($payload['question'] ?? ''));
        if ($question === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Veuillez saisir une question.',
            ], 400);
        }

        if (mb_strlen($question, 'UTF-8') > 500) {
            $question = mb_substr($question, 0, 500, 'UTF-8');
        }

        $qa = $patientResultQaService->answer($demandeAnalyse, $question);

        return $this->json([
            'ok' => true,
            'answer' => $qa['answer'],
            'disclaimer' => $qa['disclaimer'],
            'safety_notice' => $qa['safety_notice'],
            'suggested_questions' => $qa['suggested_questions'],
        ]);
    }

    /**
     * @param array<string,mixed> $llmInterpretation
     * @param array<string,mixed> $analysis
     */
    private function resolveAiSummary(array $llmInterpretation, array $analysis, string $fallback): string
    {
        $candidateFields = [
            $llmInterpretation['patient_summary'] ?? null,
            $llmInterpretation['clinician_summary'] ?? null,
            $analysis['summary'] ?? null,
            $fallback !== '' ? $fallback : null,
        ];

        foreach ($candidateFields as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $this->localizeAiText(trim($candidate));
            }
        }

        return 'Aucun resume IA disponible.';
    }

    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $llmInterpretation
     * @param array<string,mixed> $raw
     * @return array<int,string>
     */
    private function resolveAiRecommendations(array $analysis, array $llmInterpretation, array $raw): array
    {
        $recommendations = [];
        $sources = [
            $analysis['recommendations'] ?? null,
            $llmInterpretation['suggested_actions'] ?? null,
            $llmInterpretation['recommendations'] ?? null,
            $raw['recommendations'] ?? null,
        ];

        foreach ($sources as $source) {
            foreach ($this->normalizeRecommendationSource($source) as $item) {
                $recommendations[] = $this->localizeAiText($item);
            }
        }

        $recommendations = array_values(array_unique(array_filter($recommendations, static fn ($line) => trim($line) !== '')));

        if (!$recommendations) {
            $recommendations = [
                'Consultez votre medecin pour valider l\'interpretation de ce bilan.',
            ];
        }

        return array_slice($recommendations, 0, 8);
    }

    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $llmInterpretation
     * @param array<string,mixed> $raw
     * @return array<int,array{line:string,metric_name:string,level:string,is_critical:bool,priority:int}>
     */
    private function resolveAiEvidenceItems(
        ResultatAnalyse $resultatAnalyse,
        array $analysis,
        array $llmInterpretation,
        array $raw
    ): array {
        $items = [];
        $sources = [
            $resultatAnalyse->getAnomalies(),
            $analysis['anomalies'] ?? null,
            $raw['anomalies'] ?? null,
            $analysis['abnormal_values'] ?? null,
            $llmInterpretation['citations'] ?? null,
        ];

        foreach ($sources as $source) {
            foreach ($this->normalizeEvidenceSource($source) as $item) {
                $items[] = $item;
            }
        }

        $byLine = [];
        foreach ($items as $item) {
            $line = trim((string) ($item['line'] ?? ''));
            if ($line === '') {
                continue;
            }

            $key = mb_strtolower($line, 'UTF-8');
            if (!isset($byLine[$key])) {
                $byLine[$key] = $item;
                continue;
            }

            $currentPriority = (int) ($item['priority'] ?? 99);
            $existingPriority = (int) ($byLine[$key]['priority'] ?? 99);
            if ($currentPriority < $existingPriority) {
                $byLine[$key] = $item;
            }
        }

        $items = array_values($byLine);
        usort($items, static function (array $a, array $b): int {
            $priorityA = (int) ($a['priority'] ?? 99);
            $priorityB = (int) ($b['priority'] ?? 99);
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return strcasecmp((string) ($a['line'] ?? ''), (string) ($b['line'] ?? ''));
        });

        if ($items) {
            return $items;
        }

        return [[
            'line' => 'Aucune valeur hors norme detaillee n est fournie dans ce resultat.',
            'metric_name' => '',
            'level' => 'none',
            'is_critical' => false,
            'priority' => 99,
        ]];
    }

    /**
     * @param mixed $source
     * @return array<int,array{line:string,metric_name:string,level:string,is_critical:bool,priority:int}>
     */
    private function normalizeEvidenceSource(mixed $source): array
    {
        if (!is_array($source)) {
            return [];
        }

        $items = [];
        foreach ($source as $item) {
            if (is_string($item) && trim($item) !== '') {
                $normalized = $this->buildEvidenceItemFromString($item);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $normalized = $this->buildEvidenceItemFromArray($item);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $item
     * @return array{line:string,metric_name:string,level:string,is_critical:bool,priority:int}|null
     */
    private function buildEvidenceItemFromArray(array $item): ?array
    {
        $name = trim((string) ($item['name'] ?? $item['parameter'] ?? $item['label'] ?? ''));
        $value = trim((string) ($item['value'] ?? $item['observed'] ?? $item['result'] ?? ''));
        $unit = trim((string) ($item['unit'] ?? $item['units'] ?? $item['unite'] ?? ''));
        $reference = trim((string) ($item['reference'] ?? $item['range'] ?? $item['normal_range'] ?? ''));
        $status = trim((string) ($item['status'] ?? $item['flag'] ?? $item['direction'] ?? ''));
        $flag = trim((string) ($item['flag'] ?? ''));
        $severity = trim((string) ($item['severity'] ?? ''));
        $note = trim((string) ($item['note'] ?? $item['explanation'] ?? ''));

        if ($name === '' && $value === '' && $note === '') {
            return null;
        }

        if (preg_match('/^\s*flag\s*[:=]/iu', $note) === 1) {
            $note = '';
        }

        [$level, $isCritical] = $this->detectEvidenceLevelFromText(implode(' ', [$status, $flag, $severity, $note]));
        if ($level === 'unknown') {
            $numericLevel = $this->inferEvidenceLevelFromNumericRange($value, $reference);
            if ($numericLevel !== null) {
                $level = $numericLevel;
            }
        }
        if (!$isCritical && $this->shouldPromoteToCriticalFromValues($name, $value, $reference, $level)) {
            $level = 'critical';
            $isCritical = true;
        }
        $label = $this->evidenceLevelLabel($level, $isCritical);

        $line = $name !== '' ? $name : 'Valeur';
        $valueWithUnit = $this->appendEvidenceUnit($value, $unit);
        if ($valueWithUnit !== '') {
            $line .= ' : ' . $valueWithUnit;
        }

        $reference = $this->cleanupReferenceRange($reference);
        if ($reference !== '') {
            $line .= ' (ref ' . $reference . ')';
        }

        if ($label !== null) {
            $line .= ' -> ' . $label;
        }

        $line = $this->cleanupEvidenceLine($this->localizeAiText($line));
        if ($line === '') {
            return null;
        }

        return [
            'line' => $line,
            'metric_name' => $name,
            'level' => $level,
            'is_critical' => $isCritical,
            'priority' => $this->evidencePriority($level),
        ];
    }

    /**
     * @return array{line:string,metric_name:string,level:string,is_critical:bool,priority:int}|null
     */
    private function buildEvidenceItemFromString(string $rawLine): ?array
    {
        $line = $this->cleanupEvidenceLine($this->localizeAiText(trim($rawLine)));
        if ($line === '') {
            return null;
        }

        [$level, $isCritical] = $this->detectEvidenceLevelFromText($line);
        $parts = $this->extractEvidencePartsFromLine($line);
        if ($level === 'unknown') {
            $numericLevel = $this->inferEvidenceLevelFromNumericRange($parts['value'], $parts['reference']);
            if ($numericLevel !== null) {
                $level = $numericLevel;
            }
        }
        if (!$isCritical && $this->shouldPromoteToCriticalFromValues($parts['name'], $parts['value'], $parts['reference'], $level)) {
            $level = 'critical';
            $isCritical = true;
        }
        $label = $this->evidenceLevelLabel($level, $isCritical);

        $line = preg_replace('/\s*\[[^\]]+\]/u', '', $line) ?? $line;
        $line = preg_replace('/\s*-\s*(?:ELEVE|ELEVEE|BAS|FAIBLE|MOYEN|MODERE|CRITIQUE|HIGH|LOW|MEDIUM|CRITICAL|ELEVATED)\b/iu', '', $line) ?? $line;
        $line = preg_replace('/\s*->\s*(?:Critique|Eleve|Elevee|Bas|Faible|Modere|Moyen|High|Low|Medium|Critical|Elevated)\b/iu', '', $line) ?? $line;
        $line = trim($line);

        if ($label !== null) {
            $line .= ' -> ' . $label;
        }

        $line = $this->cleanupEvidenceLine($line);
        if ($line === '') {
            return null;
        }

        return [
            'line' => $line,
            'metric_name' => $parts['name'],
            'level' => $level,
            'is_critical' => $isCritical,
            'priority' => $this->evidencePriority($level),
        ];
    }

    private function appendEvidenceUnit(string $value, string $unit): string
    {
        if ($value === '' || $unit === '') {
            return $value;
        }

        if (preg_match('/' . preg_quote($unit, '/') . '/iu', $value) === 1) {
            return $value;
        }

        return $value . ' ' . $unit;
    }

    /**
     * @return array{name:string,value:string,reference:string}
     */
    private function extractEvidencePartsFromLine(string $line): array
    {
        $base = trim((string) (preg_split('/\s*->\s*/u', $line, 2)[0] ?? $line));
        if (!preg_match('/^\s*([^:]+)\s*:\s*([^()]+?)(?:\s*\(ref\s*([^)]+)\))?\s*$/iu', $base, $matches)) {
            return ['name' => '', 'value' => '', 'reference' => ''];
        }

        return [
            'name' => trim((string) ($matches[1] ?? '')),
            'value' => trim((string) ($matches[2] ?? '')),
            'reference' => trim((string) ($matches[3] ?? '')),
        ];
    }

    private function inferEvidenceLevelFromNumericRange(string $value, string $reference): ?string
    {
        $valueNumber = $this->extractFirstNumber($value);
        if ($valueNumber === null) {
            return null;
        }

        $range = $this->parseEvidenceReferenceRange($reference);
        $lower = $range['lower'];
        $upper = $range['upper'];

        if ($upper !== null && $valueNumber > $upper) {
            return 'high';
        }

        if ($lower !== null && $valueNumber < $lower) {
            return 'low';
        }

        return null;
    }

    private function shouldPromoteToCriticalFromValues(string $name, string $value, string $reference, string $level): bool
    {
        if ($level !== 'high') {
            return false;
        }

        if (!$this->isTransaminaseParameter($name)) {
            return false;
        }

        $valueNumber = $this->extractFirstNumber($value);
        if ($valueNumber === null || $valueNumber <= 0.0) {
            return false;
        }

        $range = $this->parseEvidenceReferenceRange($reference);
        $upper = $range['upper'];
        if ($upper === null || $upper <= 0.0) {
            return false;
        }

        return $valueNumber >= ($upper * 5.0);
    }

    private function isTransaminaseParameter(string $name): bool
    {
        $token = mb_strtolower(trim($name), 'UTF-8');
        if ($token === '') {
            return false;
        }

        return preg_match('/\b(asat|ast|alat|alt|transaminase)\b/u', $token) === 1;
    }

    private function extractFirstNumber(string $text): ?float
    {
        if (!preg_match('/-?\d+(?:[.,]\d+)?/u', $text, $matches)) {
            return null;
        }

        $raw = str_replace(',', '.', (string) $matches[0]);
        if (!is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * @return array{lower:?float,upper:?float}
     */
    private function parseEvidenceReferenceRange(string $reference): array
    {
        $normalized = mb_strtolower(trim($reference), 'UTF-8');
        $normalized = str_replace(['–', '—'], '-', $normalized);
        if ($normalized === '') {
            return ['lower' => null, 'upper' => null];
        }

        if (preg_match('/(?:<=|<)\s*(-?\d+(?:[.,]\d+)?)/u', $normalized, $matches)) {
            return [
                'lower' => null,
                'upper' => $this->extractFirstNumber((string) ($matches[1] ?? '')),
            ];
        }

        if (preg_match('/(?:>=|>)\s*(-?\d+(?:[.,]\d+)?)/u', $normalized, $matches)) {
            return [
                'lower' => $this->extractFirstNumber((string) ($matches[1] ?? '')),
                'upper' => null,
            ];
        }

        if (preg_match('/(-?\d+(?:[.,]\d+)?)\s*-\s*(-?\d+(?:[.,]\d+)?)/u', $normalized, $matches)) {
            return [
                'lower' => $this->extractFirstNumber((string) ($matches[1] ?? '')),
                'upper' => $this->extractFirstNumber((string) ($matches[2] ?? '')),
            ];
        }

        if (preg_match('/\b(-?\d+(?:[.,]\d+)?)\b/u', $normalized, $matches)) {
            return [
                'lower' => null,
                'upper' => $this->extractFirstNumber((string) ($matches[1] ?? '')),
            ];
        }

        return ['lower' => null, 'upper' => null];
    }

    private function cleanupReferenceRange(string $reference): string
    {
        if ($reference === '') {
            return '';
        }

        $clean = preg_replace('/^\s*(?:ref(?:erence)?\.?)\s*/iu', '', $reference) ?? $reference;
        $clean = trim($clean, " \t\n\r\0\x0B()[]");

        return $clean;
    }

    /**
     * @return array{0:string,1:bool}
     */
    private function detectEvidenceLevelFromText(string $text): array
    {
        $token = mb_strtolower($text, 'UTF-8');
        if (preg_match('/\b(critique|critical)\b/u', $token) === 1) {
            return ['critical', true];
        }

        if (preg_match('/\b(eleve|elevated|high|haut)\b/u', $token) === 1) {
            return ['high', false];
        }

        if (preg_match('/\b(bas|low|faible)\b/u', $token) === 1) {
            return ['low', false];
        }

        if (preg_match('/\b(moyen|modere|moderate|medium)\b/u', $token) === 1) {
            return ['medium', false];
        }

        return ['unknown', false];
    }

    private function evidenceLevelLabel(string $level, bool $isCritical): ?string
    {
        if ($isCritical || $level === 'critical') {
            return 'Critique';
        }

        return match ($level) {
            'high' => 'Eleve',
            'low' => 'Bas',
            'medium' => 'Modere',
            default => null,
        };
    }

    private function evidencePriority(string $level): int
    {
        return match ($level) {
            'critical' => 0,
            'high' => 1,
            'low' => 2,
            'medium' => 3,
            default => 4,
        };
    }

    private function cleanupEvidenceLine(string $line): string
    {
        $clean = preg_replace('/\s*\|\s*Flag\s*[:=]\s*[^|]+/iu', '', $line) ?? $line;
        $clean = preg_replace('/\s{2,}/u', ' ', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B|");

        return trim($clean);
    }

    private function localizeDangerLevel(?string $dangerLevel): string
    {
        $token = strtolower(trim((string) $dangerLevel));
        if ($token === '') {
            return '-';
        }

        if (in_array($token, ['critical', 'critique'], true)) {
            return 'Critique';
        }

        if (in_array($token, ['high', 'elevated', 'eleve'], true)) {
            return 'Eleve';
        }

        if (in_array($token, ['medium', 'moderate', 'modere', 'moyen'], true)) {
            return 'Modere';
        }

        if (in_array($token, ['low', 'faible', 'bas'], true)) {
            return 'Faible';
        }

        if ($token === 'unknown') {
            return 'Inconnu';
        }

        return trim((string) $dangerLevel);
    }

    private function localizeAiText(string $text): string
    {
        $localized = trim($text);
        if ($localized === '') {
            return '';
        }

        $ruleBasedSummary = $this->humanizeRuleBasedSummary($localized);
        if ($ruleBasedSummary !== null) {
            return $ruleBasedSummary;
        }

        $exact = [
            'Review elevated parameters with a clinician.' => 'Faites valider les parametres eleves par un clinicien.',
            'Review decreased parameters and correlate clinically.' => 'Faites valider les parametres abaisses et leur correlation clinique.',
            'Review elevated parameters with clinician.' => 'Faites valider les parametres eleves par un clinicien.',
            'Review decreased parameters and correlate clinically' => 'Faites valider les parametres abaisses et leur correlation clinique.',
        ];
        foreach ($exact as $source => $target) {
            if (strcasecmp($localized, $source) === 0) {
                return $target;
            }
        }

        $localized = str_ireplace('Extraction rule-based:', 'Extraction basee sur des regles :', $localized);
        $localized = str_ireplace('rule-based', 'base sur des regles', $localized);

        $localized = preg_replace('/\bHIGH\b/i', 'ELEVE', $localized) ?? $localized;
        $localized = preg_replace('/\bLOW\b/i', 'BAS', $localized) ?? $localized;
        $localized = preg_replace('/\bMEDIUM\b/i', 'MOYEN', $localized) ?? $localized;
        $localized = preg_replace('/\bUNKNOWN\b/i', 'INCONNU', $localized) ?? $localized;

        return trim($localized);
    }

    private function humanizeRuleBasedSummary(string $text): ?string
    {
        $pattern = '/(?:Extraction\s+rule-based:|Extraction\s+basee\s+sur\s+des\s+regles\s*:)\s*(\d+)\s*tests?,\s*(\d+)\s*(?:HIGH|ELEVE),\s*(\d+)\s*(?:LOW|BAS),\s*(\d+)\s*(?:UNKNOWN|INCONNU)\.?/iu';
        if (!preg_match($pattern, $text, $matches)) {
            return null;
        }

        $high = (int) ($matches[2] ?? 0);
        $low = (int) ($matches[3] ?? 0);
        $unknown = (int) ($matches[4] ?? 0);
        $outOfRange = $high + $low;

        if ($outOfRange <= 0) {
            $message = 'Les valeurs analysees semblent globalement dans les normes.';
        } elseif ($outOfRange === 1) {
            $message = 'Une valeur est hors norme.';
        } else {
            $message = 'Plusieurs valeurs sont hors norme.';
        }

        if ($high >= 3) {
            $message .= ' Une valeur est signalee critique.';
        } elseif ($high > 0) {
            $message .= ' Au moins une valeur elevee necessite une verification medicale.';
        }

        if ($unknown > 0) {
            $message .= ' Certaines valeurs n ont pas pu etre interpretees automatiquement.';
        }

        return $message;
    }

    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $raw
     * @return array<string,string>
     */
    private function resolveMetricGlossary(array $analysis, array $raw): array
    {
        $sources = [
            $raw['metric_glossary'] ?? null,
            $analysis['metric_glossary'] ?? null,
            $raw['glossary'] ?? null,
        ];

        $glossary = [];
        $normalizedToCanonical = [];

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $key => $value) {
                $metricName = '';
                $descriptionRaw = null;

                if (is_array($value)) {
                    $metricName = trim((string) ($value['name'] ?? $value['metric'] ?? $value['test'] ?? ''));
                    if ($metricName === '' && is_string($key)) {
                        $metricName = trim($key);
                    }
                    $descriptionRaw = $value['description'] ?? $value['text'] ?? $value['summary'] ?? null;
                } else {
                    if (!is_string($key)) {
                        continue;
                    }
                    $metricName = trim($key);
                    $descriptionRaw = $value;
                }

                if (!is_scalar($descriptionRaw)) {
                    continue;
                }

                $description = trim((string) $descriptionRaw);
                if ($metricName === '' || $description === '') {
                    continue;
                }

                $normalizedMetric = $this->normalizeMetricGlossaryKey($metricName);
                if ($normalizedMetric === '') {
                    continue;
                }

                if (!isset($normalizedToCanonical[$normalizedMetric])) {
                    $normalizedToCanonical[$normalizedMetric] = $metricName;
                }

                $canonicalName = $normalizedToCanonical[$normalizedMetric];
                $description = preg_replace('/\s+/u', ' ', $description) ?? $description;
                $description = $this->localizeAiText($description);
                if ($description === '') {
                    continue;
                }

                if (mb_strlen($description, 'UTF-8') > 260) {
                    $description = rtrim(mb_substr($description, 0, 257, 'UTF-8')) . '...';
                }

                if (!isset($glossary[$canonicalName])) {
                    $glossary[$canonicalName] = $description;
                }
            }
        }

        // Fallback: if IA glossary is missing, build short explanations from
        // detected metric names so the "?" helper remains available.
        $fallbackDefinitions = $this->defaultMetricGlossaryDefinitions();
        $metricNames = $this->extractMetricNamesForGlossary($analysis, $raw);
        foreach ($metricNames as $metricName) {
            $normalizedMetric = $this->normalizeMetricGlossaryKey($metricName);
            if ($normalizedMetric === '') {
                continue;
            }
            if (!isset($fallbackDefinitions[$normalizedMetric])) {
                continue;
            }
            if (!isset($normalizedToCanonical[$normalizedMetric])) {
                $normalizedToCanonical[$normalizedMetric] = $metricName;
            }

            $canonicalName = $normalizedToCanonical[$normalizedMetric];
            if (isset($glossary[$canonicalName])) {
                continue;
            }

            $glossary[$canonicalName] = $fallbackDefinitions[$normalizedMetric];
        }

        return $glossary;
    }

    private function normalizeMetricGlossaryKey(string $value): string
    {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $token = $translit !== false ? $translit : $value;
        $token = strtolower(trim($token));

        return preg_replace('/[^a-z0-9]+/', '', $token) ?? '';
    }

    /**
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $raw
     * @return array<int,string>
     */
    private function extractMetricNamesForGlossary(array $analysis, array $raw): array
    {
        $sources = [
            $analysis['anomalies'] ?? null,
            $analysis['abnormal_values'] ?? null,
            $raw['anomalies'] ?? null,
            $raw['analysis']['anomalies'] ?? null,
        ];

        $names = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            foreach ($source as $item) {
                $name = '';
                if (is_array($item)) {
                    $name = trim((string) ($item['name'] ?? $item['metric'] ?? $item['test'] ?? $item['parameter'] ?? $item['label'] ?? ''));
                } elseif (is_string($item)) {
                    $line = trim($item);
                    if ($line !== '') {
                        if (preg_match('/^([^:|]{2,80})\s*[:|]/u', $line, $match)) {
                            $name = trim((string) ($match[1] ?? ''));
                        } else {
                            $name = $line;
                        }
                    }
                }

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string,string>
     */
    private function defaultMetricGlossaryDefinitions(): array
    {
        return [
            'asat' => 'ASAT (AST): enzyme hepatique. Une hausse peut indiquer une atteinte du foie ou du muscle.',
            'ast' => 'AST (ASAT): enzyme hepatique. Une hausse peut indiquer une atteinte du foie ou du muscle.',
            'alat' => 'ALAT (ALT): enzyme du foie. Une elevation peut traduire une irritation hepatique.',
            'alt' => 'ALT (ALAT): enzyme du foie. Une elevation peut traduire une irritation hepatique.',
            'hemoglobine' => 'Hemoglobine (Hb): proteine des globules rouges qui transporte l oxygene.',
            'hb' => 'Hb (hemoglobine): proteine des globules rouges qui transporte l oxygene.',
            'leucocytes' => 'Leucocytes (WBC): globules blancs impliques dans la defense immunitaire.',
            'wbc' => 'WBC (leucocytes): globules blancs impliques dans la defense immunitaire.',
            'plaquettes' => 'Plaquettes (PLT): cellules de la coagulation qui limitent les saignements.',
            'plt' => 'PLT (plaquettes): cellules de la coagulation qui limitent les saignements.',
            'glycemieajeun' => 'Glycemie a jeun: taux de glucose sanguin apres une periode de jeune.',
            'glycemie' => 'Glycemie: taux de glucose dans le sang.',
            'creatinine' => 'Creatinine: marqueur de la fonction renale.',
            'crp' => 'CRP: proteine de l inflammation. Une hausse peut suggerer un processus inflammatoire.',
            'hba1c' => 'HbA1c: reflet de l equilibre glycemique moyen des 2 a 3 derniers mois.',
            'cholesteroltotal' => 'Cholesterol total: indicateur lipidique global a interpreter avec LDL/HDL.',
            'ldl' => 'LDL cholesterol: fraction associee au risque cardiovasculaire en cas d elevation.',
            'hdl' => 'HDL cholesterol: fraction generalement protectrice du profil lipidique.',
            'triglycerides' => 'Triglycerides: graisses sanguines, elevees en cas de risque cardio-metabolique.',
            'ferritine' => 'Ferritine: reflet des reserves en fer de l organisme.',
            'vitamined' => 'Vitamine D: intervient notamment dans la sante osseuse et immunitaire.',
            'tsh' => 'TSH: hormone de regulation thyroidienne (fonction thyroide).',
            'uree' => 'Uree: parametre utile pour evaluer l equilibre renal et metabolique.',
        ];
    }

    /**
     * @param mixed $source
     * @return array<int,string>
     */
    private function normalizeRecommendationSource(mixed $source): array
    {
        if (is_array($source)) {
            $lines = [];
            foreach ($source as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $lines[] = trim($item);
                }
            }
            return $lines;
        }

        if (!is_string($source) || trim($source) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n;]+/', $source) ?: [];
        $lines = [];
        foreach ($parts as $part) {
            $line = trim((string) $part);
            $line = ltrim($line, "-* \t");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function buildMesDemandesData(Request $request, DemandeAnalyseRepository $demandeAnalyseRepository): array
    {
        $user = $this->getTestUser();

        if (!$user instanceof Patient) {
            if ($this->isTestMode()) {
                $user = $this->entityManager->getRepository(Patient::class)->findOneBy([]);
                if (!$user) {
                    throw new AccessDeniedException('Aucun patient trouvé pour le test.');
                }
            } else {
                throw new AccessDeniedException('Accès réservé aux patients.');
            }
        }

        $allDemandes = $demandeAnalyseRepository->findBy(
            ['patient' => $user],
            ['programme_le' => 'DESC']
        );

        $typeBilanFilter = trim((string) $request->query->get('type_bilan', ''));
        $demandes = $allDemandes;

        if ($typeBilanFilter !== '') {
            $demandes = array_values(array_filter(
                $allDemandes,
                static fn (DemandeAnalyse $demande) => $demande->getTypeBilan() === $typeBilanFilter
            ));
        }

        $typeBilanOptions = [];
        foreach ($allDemandes as $demande) {
            $type = $demande->getTypeBilan();
            if ($type) {
                $typeBilanOptions[$type] = $type;
            }
        }
        ksort($typeBilanOptions);

        return [$demandes, $typeBilanOptions, $typeBilanFilter];
    }

    /**
     * Créer une nouvelle demande d'analyse pour un laboratoire spécifique
     */
    #[Route('/new/{laboratoireId}', name: 'app_demande_analyse_new_for_lab', methods: ['GET', 'POST'])]
    public function newForLab(
        Request $request, 
        EntityManagerInterface $entityManager,
        int $laboratoireId
    ): Response
    {
        $laboratoire = $entityManager->getRepository(Laboratoire::class)->find($laboratoireId);
        
        if (!$laboratoire) {
            throw $this->createNotFoundException('Laboratoire non trouvé.');
        }

        $responsable = $laboratoire->getResponsable();
        if (!$laboratoire->isDisponible() || ($responsable && !$responsable->isEstActif())) {
            $this->addFlash('error', 'Ce laboratoire est indisponible pour le moment.');
            return $this->redirectToRoute('app_labo_show', ['id' => $laboratoireId]);
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Si c'est une requête POST (formulaire simple)
        if ($request->isMethod('POST')) {
            // Vérifier le token CSRF
            $submittedToken = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('demande_analyse_new', $submittedToken)) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_labo_show', ['id' => $laboratoireId]);
            }
            
            $demandeAnalyse = new DemandeAnalyse();
            
            // Définir le laboratoire
            $demandeAnalyse->setLaboratoire($laboratoire);
            
            // Définir le type de bilan
            $typeAnalyse = $request->request->get('type_analyse');
            $demandeAnalyse->setTypeBilan($typeAnalyse ?? 'Analyse non spécifiée');
            
            // Associer le patient
            $user = $this->getUser();
            if ($user instanceof Patient) {
                $demandeAnalyse->setPatient($user);
                // IMPORTANT : Ne pas définir de médecin automatiquement
                // Le médecin reste null (optionnel)
            }
            
            // NOTE IMPORTANTE : On ne définit PAS de médecin automatiquement
            // Le champ medecin est optionnel et reste null

            $medecinId = $request->request->get('medecin_id');
            if ($medecinId) {
                $medecin = $entityManager->getRepository(Medecin::class)->find($medecinId);
                if ($medecin) {
                    $demandeAnalyse->setMedecin($medecin);
                }
            }
            
            // Ajouter les notes
            $notes = $request->request->get('notes');
            $dateSouhaitee = $request->request->get('date_souhaitee');
            $heureSouhaitee = $request->request->get('heure_souhaitee');
            
            $notesCompletes = "Date souhaitée: " . $dateSouhaitee . " à " . $heureSouhaitee;
            if ($notes) {
                $notesCompletes .= "\n" . $notes;
            }
            
            // Informations de contact supplémentaires
            $nom = $request->request->get('nom');
            $telephone = $request->request->get('telephone');
            $email = $request->request->get('email');
            
            if ($nom || $telephone || $email) {
                $notesCompletes .= "\n\n--- Informations de contact ---";
                if ($nom) $notesCompletes .= "\nNom: " . $nom;
                if ($telephone) $notesCompletes .= "\nTéléphone: " . $telephone;
                if ($email) $notesCompletes .= "\nEmail: " . $email;
            }
            
            $demandeAnalyse->setNotes($notesCompletes);
            
            // Si date programmée fournie
            if ($dateSouhaitee && $heureSouhaitee) {
                try {
                    $programmeLe = new \DateTime($dateSouhaitee . ' ' . $heureSouhaitee);
                    $demandeAnalyse->setProgrammeLe($programmeLe);
                } catch (\Exception $e) {
                    // Ignorer l'erreur de date
                }
            }
            
            // Définir la date de création
            $demandeAnalyse->setDateDemande(new \DateTimeImmutable());
            
            // Définir le statut par défaut
            $demandeAnalyse->setStatut('en_attente');
            
            $entityManager->persist($demandeAnalyse);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande d\'analyse a été créée avec succès.');

            // Rediriger selon le type d'utilisateur
            if ($user instanceof Patient || $this->isTestMode()) {
                return $this->redirectToRoute('app_demande_analyse_mes_demandes');
            } else {
                return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
            }
        }

        // Si c'est une requête GET, afficher le formulaire Symfony complet
        $demandeAnalyse = new DemandeAnalyse();
        $demandeAnalyse->setLaboratoire($laboratoire);
        
        $user = $this->getUser();
        
        if ($user instanceof Patient) {
            $demandeAnalyse->setPatient($user);
            // IMPORTANT : Ne pas définir de médecin automatiquement
        }

        $userRole = $user ? $user->getRoles()[0] : 'ROLE_PATIENT';
        
        $form = $this->createForm(DemandeAnalyseType::class, $demandeAnalyse, [
            'user_role' => $userRole,
            'user_entity' => $user,
            'laboratoire' => $laboratoire,
        ]);
        
        return $this->render('demande_analyse/new.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'form' => $form->createView(),
            'laboratoire' => $laboratoire,
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Afficher les détails d'une demande d'analyse
     */
    #[Route('/{id}', name: 'app_demande_analyse_show', methods: ['GET'])]
    public function show(DemandeAnalyse $demandeAnalyse): Response
    {
        // En mode test, on autorise l'accès sans vérification stricte
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }

        return $this->render('demande_analyse/show.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Modifier une demande d'analyse
     */
    #[Route('/{id}/edit', name: 'app_demande_analyse_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        DemandeAnalyse $demandeAnalyse, 
        EntityManagerInterface $entityManager
    ): Response
    {
        // En mode test, on autorise l'accès sans vérification stricte
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }
        
        // Empêcher la modification si un resultat PDF existe
        if ($demandeAnalyse->getResultatPdf()) {
            $this->addFlash('warning', 'Cette demande a déjà un resultat et ne peut plus être modifiée.');
            return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
        }
        

        $user = $this->getTestUser();
        $userRole = $user ? $user->getRoles()[0] : 'ROLE_PATIENT';

        $form = $this->createForm(DemandeAnalyseType::class, $demandeAnalyse, [
            'user_role' => $userRole,
            'user_entity' => $user,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Mettre à jour la date de modification
                // Vous pouvez ajouter un champ date_modification si nécessaire
                
                $entityManager->flush();

                $this->addFlash('success', 'Demande d\'analyse mise à jour avec succès.');

                if ($user instanceof Patient || $this->isTestMode()) {
                    return $this->redirectToRoute('app_demande_analyse_mes_demandes');
                } else {
                    return $this->redirectToRoute('app_demande_analyse_index');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('demande_analyse/edit.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'form' => $form->createView(),
            'test_mode' => $this->isTestMode(),
        ]);
    }

    /**
     * Supprimer une demande d'analyse
     */
    #[Route('/{id}/delete', name: 'app_demande_analyse_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        DemandeAnalyse $demandeAnalyse, 
        EntityManagerInterface $entityManager
    ): Response
    {
        // En mode test, on autorise l'accès sans vérification stricte
        if (!$this->isTestMode()) {
            $this->checkAccess($demandeAnalyse);
        }
        
        // Empêcher la suppression si un resultat PDF existe
        if ($demandeAnalyse->getResultatPdf()) {
            $this->addFlash('warning', 'Cette demande a déjà un resultat et ne peut plus être supprimée.');
            
            $user = $this->getTestUser();
            if ($user instanceof Patient || $this->isTestMode()) {
                return $this->redirectToRoute('app_demande_analyse_mes_demandes');
            } else {
                return $this->redirectToRoute('app_demande_analyse_index');
            }
        }
        

        if ($this->isCsrfTokenValid('delete'.$demandeAnalyse->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($demandeAnalyse);
                $entityManager->flush();
                
                $this->addFlash('success', 'Demande d\'analyse supprimée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        $user = $this->getTestUser();
        if ($user instanceof Patient || $this->isTestMode()) {
            return $this->redirectToRoute('app_demande_analyse_mes_demandes');
        } else {
            return $this->redirectToRoute('app_demande_analyse_index');
        }
    }

    /**
     * Changer le statut d'une demande d'analyse (pour médecins et administrateurs)
     */
    #[Route('/{id}/changer-statut/{statut}', name: 'app_demande_analyse_changer_statut', methods: ['POST'])]
    public function changerStatut(
        Request $request,
        DemandeAnalyse $demandeAnalyse,
        string $statut,
        EntityManagerInterface $entityManager
    ): Response
    {
        // En mode test, on autorise tout le monde
        if (!$this->isTestMode()) {
            // Seuls les médecins et administrateurs peuvent changer le statut
            if (!$this->isGranted('ROLE_MEDECIN') && !$this->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedException('Vous n\'avez pas la permission de modifier le statut.');
            }
        }

        if ($this->isCsrfTokenValid('changer-statut'.$demandeAnalyse->getId(), $request->request->get('_token'))) {
            $statutsValides = ['en_attente', 'envoye'];

            if (!in_array($statut, $statutsValides, true)) {
                $this->addFlash('error', 'Statut invalide.');
                return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
            }

            $statut = $demandeAnalyse->getResultatPdf() ? 'envoye' : 'en_attente';
            $demandeAnalyse->setStatut($statut);

            // Mettre à jour la date d'envoi si resultat PDF disponible
            if ($statut === 'envoye' && !$demandeAnalyse->getEnvoyeLe()) {
                $demandeAnalyse->setEnvoyeLe(new \DateTime());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Statut de la demande mis à jour avec succès.');
        }

        return $this->redirectToRoute('app_demande_analyse_show', ['id' => $demandeAnalyse->getId()]);
    }

    /**
     * Vérifier l'accès à une demande d'analyse
     */
    private function checkAccess(DemandeAnalyse $demandeAnalyse): void
    {
        $user = $this->getUser();

        // Les administrateurs ont accès à tout
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Les médecins ne peuvent voir que leurs propres demandes
        if ($this->isGranted('ROLE_MEDECIN') && $user instanceof Medecin) {
            if ($demandeAnalyse->getMedecin() !== $user) {
                throw new AccessDeniedException('Vous n\'avez pas accès à cette demande.');
            }
            return;
        }

        // Les patients ne peuvent voir que leurs propres demandes
        if ($this->isGranted('ROLE_PATIENT') && $user instanceof Patient) {
            if ($demandeAnalyse->getPatient() !== $user) {
                throw new AccessDeniedException('Vous n\'avez pas accès à cette demande.');
            }
            return;
        }

        throw new AccessDeniedException('Accès non autorisé.');
    }

    /**
     * Créer une nouvelle demande d'analyse
     */
    #[Route('/new', name: 'app_demande_analyse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $demandeAnalyse = new DemandeAnalyse();
        
        $user = $this->getTestUser();
        $isTestMode = $this->isTestMode();
        
        if ($isTestMode) {
            // En mode test, chercher des entités par défaut
            if (!$user || !$user instanceof Patient) {
                $patient = $entityManager->getRepository(Patient::class)->findOneBy([]);
                if ($patient) {
                    $demandeAnalyse->setPatient($patient);
                    $user = $patient;
                }
            } else {
                $demandeAnalyse->setPatient($user);
            }
            
            // IMPORTANT : Ne pas chercher un médecin par défaut
            // Le médecin reste null (optionnel)
            
            // Chercher un laboratoire existant pour tester
            $laboratoire = $entityManager->getRepository(Laboratoire::class)->findOneBy([]);
            if ($laboratoire) {
                $demandeAnalyse->setLaboratoire($laboratoire);
            }
        } else {
            // EN PRODUCTION : utiliser l'utilisateur connecté
            if ($user instanceof Patient) {
                $demandeAnalyse->setPatient($user);
                // IMPORTANT : Ne pas définir de médecin automatiquement
            }

            if ($user instanceof Medecin) {
                // Si c'est un médecin qui crée la demande, on l'associe comme médecin
                $demandeAnalyse->setMedecin($user);
            }
        }

        // Déterminer le rôle pour le formulaire
        $userRole = $user ? $user->getRoles()[0] : 'ROLE_PATIENT';

        $form = $this->createForm(DemandeAnalyseType::class, $demandeAnalyse, [
            'user_role' => $userRole,
            'user_entity' => $user,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Si mode test et pas de patient, chercher un patient par défaut
                if ($isTestMode && !$demandeAnalyse->getPatient()) {
                    $defaultPatient = $entityManager->getRepository(Patient::class)->findOneBy([]);
                    if ($defaultPatient) {
                        $demandeAnalyse->setPatient($defaultPatient);
                    } else {
                        throw new \Exception('Aucun patient disponible pour le test.');
                    }
                }
                
                // Vérifications
                if (!$demandeAnalyse->getPatient()) {
                    throw new \Exception('Un patient doit être sélectionné.');
                }
                
                if (!$demandeAnalyse->getLaboratoire()) {
                    throw new \Exception('Un laboratoire doit être sélectionné.');
                }
                
                // Définir la date de création
                $demandeAnalyse->setDateDemande(new \DateTimeImmutable());
                
                // Définir le statut par défaut
                $demandeAnalyse->setStatut('en_attente');
                
                $entityManager->persist($demandeAnalyse);
                $entityManager->flush();

                $this->addFlash('success', 'Demande d\'analyse créée avec succès.');

                // Redirection
                if ($user instanceof Patient || $isTestMode) {
                    return $this->redirectToRoute('app_demande_analyse_mes_demandes');
                } else {
                    return $this->redirectToRoute('app_demande_analyse_index');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }
        }

        return $this->render('demande_analyse/new.html.twig', [
            'demande_analyse' => $demandeAnalyse,
            'form' => $form->createView(),
            'test_mode' => $isTestMode,
        ]);
    }

}


