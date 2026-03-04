<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\ResultatAnalyse;

class PatientResultQaService
{
    private const DISCLAIMER = 'Assistant d information uniquement. Cette reponse ne remplace pas un avis medical, un diagnostic ou une prescription.';

    /**
     * @return array{
     *   answer: string,
     *   disclaimer: string,
     *   safety_notice: ?string,
     *   suggested_questions: array<int, string>
     * }
     */
    public function answer(DemandeAnalyse $demandeAnalyse, string $question): array
    {
        $resultat = $demandeAnalyse->getResultatAnalyse();
        if (!$resultat instanceof ResultatAnalyse) {
            return [
                'answer' => 'Aucun resultat IA exploitable pour cette demande.',
                'disclaimer' => self::DISCLAIMER,
                'safety_notice' => null,
                'suggested_questions' => $this->defaultSuggestedQuestions(),
            ];
        }

        $question = trim($question);
        $normalized = $this->normalize($question);
        $summary = $this->extractSummary($resultat);
        $recommendations = $this->extractRecommendations($resultat);
        $dangerLevel = $resultat->getDangerLevel();
        $dangerScore = $resultat->getDangerScore();
        $riskSentence = $this->buildRiskSentence($dangerLevel, $dangerScore);

        $answer = $this->buildGenericAnswer($summary, $riskSentence, $recommendations);

        if ($this->containsAny($normalized, [
            'traitement', 'medicament', 'dose', 'posologie', 'ordonnance', 'antibiotique',
            'que dois je prendre', 'quel medicament', 'augmenter', 'diminuer',
        ])) {
            $answer = 'Je ne peux pas proposer de traitement, dose ou modification de medicaments. '
                . 'Cette decision doit etre prise par votre medecin avec votre contexte clinique complet. '
                . $riskSentence;
        } elseif ($this->containsAny($normalized, [
            'que signifie', 'ca veut dire', 'veut dire', 'signifie', 'normal', 'grave', 'interpret',
        ])) {
            $answer = $this->buildMeaningAnswer($summary, $riskSentence);
        } elseif ($this->containsAny($normalized, [
            'surveiller', 'faire attention', 'risque', 'anomalie', 'anormal', 'symptome',
        ])) {
            $answer = $this->buildMonitoringAnswer($summary, $riskSentence, $recommendations);
        } elseif ($this->containsAny($normalized, [
            'quand', 'reconsulter', 'rdv', 'rendez vous', 'consulter', 'delai',
        ])) {
            $answer = $this->buildFollowUpAnswer($riskSentence, $dangerScore);
        }

        return [
            'answer' => trim($answer),
            'disclaimer' => self::DISCLAIMER,
            'safety_notice' => $this->buildSafetyNotice($normalized, $dangerScore),
            'suggested_questions' => $this->defaultSuggestedQuestions(),
        ];
    }

    private function buildMeaningAnswer(string $summary, string $riskSentence): string
    {
        return trim(
            sprintf(
                "En langage simple: %s %s Cette lecture reste informative et doit etre confirmee en consultation.",
                $summary,
                $riskSentence
            )
        );
    }

    /**
     * @param array<int, string> $recommendations
     */
    private function buildMonitoringAnswer(string $summary, string $riskSentence, array $recommendations): string
    {
        $lines = [
            'Points principaux a retenir: ' . $summary,
            $riskSentence,
        ];

        if ($recommendations) {
            $lines[] = 'Points a surveiller en priorite: ' . $this->joinRecommendations($recommendations);
        } else {
            $lines[] = 'Surveillez toute aggravation de vos symptomes et gardez votre suivi medical.';
        }

        $lines[] = 'En cas de doute clinique, demandez un avis medical.';

        return implode(' ', $lines);
    }

    private function buildFollowUpAnswer(string $riskSentence, ?int $dangerScore): string
    {
        if ($dangerScore !== null && $dangerScore >= 85) {
            $timing = 'Un avis medical rapide est recommande aujourd hui.';
        } elseif ($dangerScore !== null && $dangerScore >= 65) {
            $timing = 'Prenez un rendez-vous rapidement, idealement sous 24h.';
        } elseif ($dangerScore !== null && $dangerScore >= 35) {
            $timing = 'Planifiez un controle prochain avec votre medecin.';
        } else {
            $timing = 'Un suivi programme avec votre medecin reste recommande.';
        }

        return trim($riskSentence . ' ' . $timing . ' Ne retardez pas la consultation si les symptomes s aggravent.');
    }

    /**
     * @param array<int, string> $recommendations
     */
    private function buildGenericAnswer(string $summary, string $riskSentence, array $recommendations): string
    {
        $message = sprintf('%s %s', $summary, $riskSentence);
        if ($recommendations) {
            $message .= ' Reperes utiles: ' . $this->joinRecommendations($recommendations) . '.';
        }
        $message .= ' Pour une decision medicale personnalisee, consultez votre medecin.';
        return trim($message);
    }

    private function buildRiskSentence(?string $dangerLevel, ?int $dangerScore): string
    {
        $level = $this->localizeDangerLevel($dangerLevel);
        if ($dangerScore === null && $level === '') {
            return 'Le niveau de risque n est pas precise automatiquement.';
        }

        if ($dangerScore !== null) {
            if ($level === '') {
                return sprintf('Le score de risque detecte est %d/100.', $dangerScore);
            }
            return sprintf('Le niveau de risque detecte est %s (%d/100).', $level, $dangerScore);
        }

        return sprintf('Le niveau de risque detecte est %s.', $level);
    }

    private function buildSafetyNotice(string $normalizedQuestion, ?int $dangerScore): ?string
    {
        if (
            $this->containsAny($normalizedQuestion, ['urgence', 'urgent', 'samu', 'hopital', 'douleur thoracique', 'essoufflement'])
            || ($dangerScore !== null && $dangerScore >= 85)
        ) {
            return 'Si vous avez douleur thoracique, gene respiratoire, malaise, saignement important ou aggravation rapide, contactez immediatement les urgences.';
        }

        if ($dangerScore !== null && $dangerScore >= 65) {
            return 'Prenez un avis medical rapidement, meme si vous vous sentez stable.';
        }

        return null;
    }

    private function extractSummary(ResultatAnalyse $resultat): string
    {
        $raw = $resultat->getAiRawResponse();
        if (!is_array($raw)) {
            $raw = [];
        }

        $analysis = is_array($raw['analysis'] ?? null) ? $raw['analysis'] : [];
        $llm = is_array($raw['llm_interpretation'] ?? null) ? $raw['llm_interpretation'] : [];
        $fallback = trim((string) $resultat->getResumeBilan());

        $fields = [
            $llm['patient_summary'] ?? null,
            $llm['clinician_summary'] ?? null,
            $analysis['summary'] ?? null,
            $fallback !== '' ? $fallback : null,
        ];

        foreach ($fields as $field) {
            if (is_string($field) && trim($field) !== '') {
                return $this->localizeAiText(trim($field));
            }
        }

        return 'Aucun resume detaille n est disponible pour ce document.';
    }

    /**
     * @return array<int, string>
     */
    private function extractRecommendations(ResultatAnalyse $resultat): array
    {
        $raw = $resultat->getAiRawResponse();
        if (!is_array($raw)) {
            $raw = [];
        }

        $analysis = is_array($raw['analysis'] ?? null) ? $raw['analysis'] : [];
        $llm = is_array($raw['llm_interpretation'] ?? null) ? $raw['llm_interpretation'] : [];

        $sources = [
            $analysis['recommendations'] ?? null,
            $llm['suggested_actions'] ?? null,
            $llm['recommendations'] ?? null,
            $raw['recommendations'] ?? null,
        ];

        $items = [];
        foreach ($sources as $source) {
            foreach ($this->normalizeRecommendationSource($source) as $line) {
                $items[] = $this->localizeAiText($line);
            }
        }

        $items = array_values(array_unique(array_filter($items, static fn (string $line): bool => trim($line) !== '')));
        return array_slice($items, 0, 4);
    }

    /**
     * @param mixed $source
     * @return array<int, string>
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

    /**
     * @param array<int, string> $recommendations
     */
    private function joinRecommendations(array $recommendations): string
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $recommendations
        ))));

        $subset = array_slice($normalized, 0, 3);
        if (!$subset) {
            return 'Aucun point specifique disponible';
        }

        return implode(' | ', $subset);
    }

    /**
     * @return array<int, string>
     */
    private function defaultSuggestedQuestions(): array
    {
        return [
            'Que signifie ce resultat pour moi ?',
            'Quels points dois-je surveiller ?',
            'Quand dois-je reconsulter ?',
        ];
    }

    private function localizeDangerLevel(?string $dangerLevel): string
    {
        $token = strtolower(trim((string) $dangerLevel));
        if ($token === '') {
            return '';
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

        $high = (int) $matches[2];
        $low = (int) $matches[3];
        $unknown = (int) $matches[4];
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
     * @param array<int, string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            $n = $this->normalize((string) $needle);
            if ($n !== '' && str_contains($text, $n)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return trim($value);
    }
}
