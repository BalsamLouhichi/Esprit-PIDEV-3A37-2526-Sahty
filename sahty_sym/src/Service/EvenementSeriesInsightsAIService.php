<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\InscriptionEvenement;
use App\Repository\EvenementRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EvenementSeriesInsightsAIService
{
    private const FREE_AI_URL = 'https://text.pollinations.ai';

    public function __construct(
        private readonly EvenementRepository $evenementRepository,
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function analyzeSeriesForEvent(Evenement $referenceEvent): array
    {
        $seriesBase = $this->extractSeriesBase((string) $referenceEvent->getTitre());
        $seriesEvents = $this->findSeriesEvents($referenceEvent, $seriesBase);

        $editions = [];
        $participantHistory = [];
        $topicScores = [];
        $audienceScores = [];
        $allParticipantIds = [];
        $previousParticipantIds = [];
        $retentionRates = [];

        foreach ($seriesEvents as $index => $event) {
            $participantRoles = [];
            $participantIds = [];

            foreach ($event->getInscriptions() as $inscription) {
                if (!$inscription instanceof InscriptionEvenement) {
                    continue;
                }

                $user = $inscription->getUtilisateur();
                if ($user === null || $user->getId() === null) {
                    continue;
                }

                $participantIds[] = (int) $user->getId();
                $allParticipantIds[] = (int) $user->getId();
                $participantRoles[] = (string) $user->getRole();
            }

            $participantIds = array_values(array_unique($participantIds));
            $participantsCount = count($participantIds);

            if (count($previousParticipantIds) > 0) {
                $returning = count(array_intersect($previousParticipantIds, $participantIds));
                $retentionRates[] = ($returning / max(count($previousParticipantIds), 1)) * 100;
            }
            $previousParticipantIds = $participantIds;

            $topicScore = $this->scoreTopicComplexity($event);
            $audienceScore = $this->scoreAudienceSpecialization($participantRoles, $event);

            $topicScores[] = $topicScore;
            $audienceScores[] = $audienceScore;
            $participantHistory[] = $participantsCount;

            $detectedEdition = $this->extractEditionNumber((string) $event->getTitre()) ?? ($index + 1);

            $editions[] = [
                'id' => $event->getId(),
                'numero' => $detectedEdition,
                'titre' => (string) $event->getTitre(),
                'date' => $event->getDateDebut()?->format('d/m/Y'),
                'participants' => $participantsCount,
                'dna' => $this->buildEditionDna($topicScore, $audienceScore, $event),
            ];
        }

        usort($editions, static fn (array $a, array $b): int => ($a['numero'] <=> $b['numero']));

        $growth = $this->buildGrowthStats($participantHistory);
        $knowledge = $this->buildKnowledgeProgression($topicScores);
        $audienceEvolution = $this->buildAudienceEvolution($audienceScores);
        $loyalty = $this->buildLoyaltyMetrics($allParticipantIds, $retentionRates, $seriesEvents);

        $fallbackSynthesis = $this->buildSeriesSynthesis($growth, $knowledge, $audienceEvolution, $loyalty);
        $aiSynthesis = $this->buildExternalAiSeriesSynthesis(
            $referenceEvent,
            $editions,
            $growth,
            $knowledge,
            $audienceEvolution,
            $loyalty
        );

        return [
            'series_nom' => $this->humanizeSeriesName($seriesBase, (string) $referenceEvent->getTitre()),
            'is_series' => count($seriesEvents) > 1,
            'editions_count' => count($seriesEvents),
            'editions' => $editions,
            'croissance' => $growth,
            'evolution_thematique' => [
                'niveau' => $this->mapTopicTrendLabel($topicScores),
                'resume' => $this->buildTopicSummary($topicScores),
            ],
            'evolution_audience' => $audienceEvolution,
            'progression_pedagogique' => $knowledge,
            'fidelite' => $loyalty,
            'synthese' => $aiSynthesis ?? $fallbackSynthesis,
            'synthese_source' => $aiSynthesis !== null ? 'external_api' : 'local_fallback',
        ];
    }

    private function buildExternalAiSeriesSynthesis(
        Evenement $referenceEvent,
        array $editions,
        array $growth,
        array $knowledge,
        array $audienceEvolution,
        array $loyalty
    ): ?array {
        try {
            $compactEditions = array_map(static function (array $edition): array {
                return [
                    'numero' => (int) ($edition['numero'] ?? 0),
                    'titre' => (string) ($edition['titre'] ?? ''),
                    'participants' => (int) ($edition['participants'] ?? 0),
                ];
            }, $editions);

            $payload = [
                'titre_reference' => (string) $referenceEvent->getTitre(),
                'editions' => $compactEditions,
                'croissance' => $growth,
                'progression_pedagogique' => $knowledge,
                'evolution_audience' => $audienceEvolution,
                'fidelite' => $loyalty,
            ];

            $prompt = 'Tu es analyste d editions d evenements medicaux. '
                .'Reponds uniquement en JSON avec les cles "niveau" et "message". '
                .'Le message doit etre en francais, court (max 180 caracteres), actionnable, sans emojis. '
                .'Niveaux autorises: excellent, bon, moyen, faible. '
                .'Donnees: '.json_encode($payload, JSON_UNESCAPED_UNICODE);

            $response = $this->httpClient->request(
                'GET',
                self::FREE_AI_URL.'/'.rawurlencode($prompt),
                [
                    'headers' => ['Accept' => 'application/json,text/plain'],
                    'timeout' => 8,
                ]
            );

            $raw = trim($response->getContent(false));
            if ($raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded) && preg_match('/\{[\s\S]*\}/', $raw, $m) === 1) {
                $decoded = json_decode((string) $m[0], true);
            }
            if (!is_array($decoded)) {
                return null;
            }

            $niveau = mb_strtolower(trim((string) ($decoded['niveau'] ?? '')));
            $message = trim((string) ($decoded['message'] ?? ''));

            if ($message === '') {
                return null;
            }
            if (!in_array($niveau, ['excellent', 'bon', 'moyen', 'faible'], true)) {
                $niveau = 'moyen';
            }

            return [
                'niveau' => $niveau,
                'message' => mb_substr($message, 0, 180),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function buildNextEditionTitle(Evenement $event): string
    {
        $currentTitle = (string) $event->getTitre();
        $currentEdition = $this->extractEditionNumber($currentTitle);

        if ($currentEdition !== null) {
            $nextEdition = $currentEdition + 1;
            return (string) preg_replace('/(edition|ed\.?|edt)\s*#?\s*\d+/iu', 'Edition '.$nextEdition, $currentTitle);
        }

        return trim($currentTitle).' - Edition 2';
    }

    private function findSeriesEvents(Evenement $referenceEvent, string $seriesBase): array
    {
        $sameTypeEvents = $this->evenementRepository->findBy(
            ['type' => $referenceEvent->getType()],
            ['dateDebut' => 'ASC']
        );

        $matches = [];
        foreach ($sameTypeEvents as $candidate) {
            $candidateBase = $this->extractSeriesBase((string) $candidate->getTitre());
            if ($candidateBase === '') {
                continue;
            }

            similar_text($seriesBase, $candidateBase, $similarity);
            $isStrongMatch = $candidateBase === $seriesBase || $similarity >= 58.0;
            $isContainMatch = str_contains($candidateBase, $seriesBase) || str_contains($seriesBase, $candidateBase);

            if ($isStrongMatch || $isContainMatch) {
                $matches[] = $candidate;
            }
        }

        if (count($matches) === 0) {
            $matches[] = $referenceEvent;
        }

        usort($matches, static function (Evenement $a, Evenement $b): int {
            $aDate = $a->getDateDebut()?->getTimestamp() ?? 0;
            $bDate = $b->getDateDebut()?->getTimestamp() ?? 0;
            return $aDate <=> $bDate;
        });

        return $matches;
    }

    private function extractSeriesBase(string $title): string
    {
        $value = mb_strtolower(trim($title));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/(edition|ed\.?|edt)\s*#?\s*\d+/iu', '', $value) ?? $value;
        $value = preg_replace('/\b(20\d{2})\b/u', '', $value) ?? $value;
        $value = preg_replace('/[\(\)\[\]\-_:,;|]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function extractEditionNumber(string $title): ?int
    {
        if (preg_match('/(edition|ed\.?|edt)\s*#?\s*(\d+)/iu', $title, $matches) === 1) {
            return (int) $matches[2];
        }

        return null;
    }

    private function scoreTopicComplexity(Evenement $event): int
    {
        $content = mb_strtolower(trim((string) $event->getTitre().' '.(string) $event->getDescription()));
        $score = 1;

        $advancedKeywords = [
            'avance',
            'avancée',
            'expert',
            'protocole',
            'cas clinique',
            'complexe',
            'chirurgie',
            'specialise',
            'spécialisé',
            'technique',
        ];
        $basicKeywords = [
            'introduction',
            'initiation',
            'bases',
            'fondamentaux',
            'prevention',
            'prévention',
            'sensibilisation',
            'debutant',
            'débutant',
        ];

        foreach ($advancedKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                $score += 1;
            }
        }
        foreach ($basicKeywords as $keyword) {
            if (str_contains($content, $keyword)) {
                $score -= 1;
            }
        }

        return max(1, min(5, $score));
    }

    private function scoreAudienceSpecialization(array $participantRoles, Evenement $event): int
    {
        $score = 1;

        foreach ($participantRoles as $role) {
            if (in_array($role, ['medecin', 'responsable_labo', 'responsable_para'], true)) {
                $score += 1;
            }
        }

        foreach ($event->getGroupeCibles() as $groupe) {
            $name = mb_strtolower((string) $groupe->getNom());
            if (str_contains($name, 'medecin') || str_contains($name, 'professionnel')) {
                $score += 1;
            }
            if (str_contains($name, 'etudiant') || str_contains($name, 'debutant')) {
                $score -= 1;
            }
        }

        return max(1, min(5, $score));
    }

    private function buildEditionDna(int $topicScore, int $audienceScore, Evenement $event): array
    {
        $description = mb_strtolower((string) $event->getDescription());

        return [
            'difficulte' => match (true) {
                $topicScore >= 4 => 'Avance',
                $topicScore === 3 => 'Intermediaire',
                default => 'Accessible',
            },
            'profondeur_scientifique' => match (true) {
                $topicScore >= 4 => 'Elevee',
                $topicScore === 3 => 'Moyenne',
                default => 'Fondamentale',
            },
            'intensite_pratique' => str_contains($description, 'atelier') || str_contains($description, 'cas')
                ? 'Forte'
                : 'Moderée',
            'public_cible' => match (true) {
                $audienceScore >= 4 => 'Specialistes',
                $audienceScore === 3 => 'Mixte',
                default => 'Large public',
            },
        ];
    }

    private function buildGrowthStats(array $participantHistory): array
    {
        if (count($participantHistory) < 2) {
            return [
                'tendance' => 'Donnees insuffisantes',
                'pourcentage' => 0,
                'resume' => 'Une seule edition disponible pour le moment.',
            ];
        }

        $first = (int) ($participantHistory[0] ?? 0);
        $last = (int) ($participantHistory[count($participantHistory) - 1] ?? 0);
        $percentage = $first > 0 ? (($last - $first) / $first) * 100 : 0;

        $trend = match (true) {
            $percentage >= 25 => 'Croissance forte',
            $percentage >= 5 => 'Croissance moderee',
            $percentage <= -10 => 'Recul',
            default => 'Stable',
        };

        return [
            'tendance' => $trend,
            'pourcentage' => round($percentage, 1),
            'resume' => sprintf(
                'Participation de %d a %d participants (%s%%).',
                $first,
                $last,
                number_format(round($percentage, 1), 1, ',', ' ')
            ),
        ];
    }

    private function buildKnowledgeProgression(array $topicScores): array
    {
        if (count($topicScores) < 2) {
            return [
                'niveau' => 'Non evalue',
                'message' => 'Ajoutez plus d\'editions pour evaluer la progression pedagogique.',
            ];
        }

        $improving = 0;
        $dropping = 0;

        for ($i = 1; $i < count($topicScores); $i++) {
            if ($topicScores[$i] > $topicScores[$i - 1]) {
                $improving++;
            } elseif ($topicScores[$i] < $topicScores[$i - 1]) {
                $dropping++;
            }
        }

        if ($improving >= 2 && $dropping === 0) {
            return [
                'niveau' => 'Fort',
                'message' => 'Le parcours construit clairement des competences avancees edition apres edition.',
            ];
        }

        if ($improving > $dropping) {
            return [
                'niveau' => 'Modere',
                'message' => 'La progression est presente mais reste partiellement irréguliere.',
            ];
        }

        return [
            'niveau' => 'Faible',
            'message' => 'Aucune progression claire detectee entre les editions.',
        ];
    }

    private function buildAudienceEvolution(array $audienceScores): array
    {
        if (count($audienceScores) < 2) {
            return [
                'niveau' => 'Stable',
                'message' => 'Donnees insuffisantes pour qualifier l\'evolution du public.',
            ];
        }

        $start = $audienceScores[0];
        $end = $audienceScores[count($audienceScores) - 1];

        if ($end - $start >= 2) {
            return [
                'niveau' => 'Specialisation croissante',
                'message' => 'Le public evolue vers des profils plus experts.',
            ];
        }

        if ($end - $start <= -2) {
            return [
                'niveau' => 'Ouverture',
                'message' => 'Le public devient plus large et moins specialise.',
            ];
        }

        return [
            'niveau' => 'Stable',
            'message' => 'Le niveau de specialisation du public reste globalement constant.',
        ];
    }

    private function buildTopicSummary(array $topicScores): string
    {
        if (count($topicScores) < 2) {
            return 'Pas assez d\'editions pour observer une trajectoire thematique.';
        }

        $start = $topicScores[0];
        $end = $topicScores[count($topicScores) - 1];

        if ($end > $start) {
            return 'Les contenus evoluent vers des sujets plus avances.';
        }

        if ($end < $start) {
            return 'Les contenus recents sont plus introductifs que les premieres editions.';
        }

        return 'Le niveau thematique reste globalement stable dans la serie.';
    }

    private function mapTopicTrendLabel(array $topicScores): string
    {
        if (count($topicScores) < 2) {
            return 'Non determine';
        }

        $start = $topicScores[0];
        $end = $topicScores[count($topicScores) - 1];
        if ($end > $start) {
            return 'Montee en expertise';
        }
        if ($end < $start) {
            return 'Retour a des bases';
        }

        return 'Stabilite';
    }

    private function buildLoyaltyMetrics(array $allParticipantIds, array $retentionRates, array $seriesEvents): array
    {
        $editionCount = count($seriesEvents);
        if ($editionCount < 2) {
            return [
                'retention_moyenne' => 0,
                'dropoff_moyen' => 0,
                'participants_recurrents' => 0,
                'message' => 'La fidelite se mesure a partir de 2 editions minimum.',
            ];
        }

        $totalOccurrencesByUser = [];
        foreach ($allParticipantIds as $id) {
            if (!isset($totalOccurrencesByUser[$id])) {
                $totalOccurrencesByUser[$id] = 0;
            }
            $totalOccurrencesByUser[$id]++;
        }

        $recurrentParticipants = 0;
        foreach ($totalOccurrencesByUser as $occurrences) {
            if ($occurrences > 1) {
                $recurrentParticipants++;
            }
        }

        $retentionAverage = count($retentionRates) > 0 ? array_sum($retentionRates) / count($retentionRates) : 0.0;
        $dropoffAverage = max(0.0, 100.0 - $retentionAverage);

        return [
            'retention_moyenne' => round($retentionAverage, 1),
            'dropoff_moyen' => round($dropoffAverage, 1),
            'participants_recurrents' => $recurrentParticipants,
            'message' => sprintf(
                '%s%% de retention moyenne entre editions, %s%% de decrochage moyen.',
                number_format(round($retentionAverage, 1), 1, ',', ' '),
                number_format(round($dropoffAverage, 1), 1, ',', ' ')
            ),
        ];
    }

    private function buildSeriesSynthesis(array $growth, array $knowledge, array $audienceEvolution, array $loyalty): string
    {
        $parts = [];
        $parts[] = sprintf('Tendance: %s.', $growth['tendance']);
        $parts[] = sprintf('Progression pedagogique: %s.', $knowledge['niveau']);
        $parts[] = sprintf('Audience: %s.', $audienceEvolution['niveau']);
        $parts[] = sprintf('Fidelite: %.1f%% de retention moyenne.', (float) ($loyalty['retention_moyenne'] ?? 0));

        return implode(' ', $parts);
    }

    private function humanizeSeriesName(string $seriesBase, string $fallbackTitle): string
    {
        if ($seriesBase === '') {
            return $fallbackTitle;
        }

        return mb_convert_case($seriesBase, MB_CASE_TITLE, 'UTF-8');
    }
}
