<?php

namespace App\Service;

use App\Entity\Evenement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EvenementExperienceDesignService
{
    public function __construct(
        private readonly ?HttpClientInterface $httpClient = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExperiencePack(Evenement $evenement): array
    {
        $external = $this->buildExperiencePackFromExternalApi($evenement);
        if ($external !== null) {
            return $this->normalizePack($external, $evenement);
        }

        $durationMinutes = $this->computeDurationMinutes($evenement);
        $phases = $this->extractPhasesFromDescription((string) $evenement->getDescription());
        if (count($phases) < 3) {
            $phases = $this->defaultPhasesByType((string) $evenement->getType());
        }

        $phases = array_slice($phases, 0, 7);
        $phasesWithTiming = $this->allocateTiming($phases, $durationMinutes, $evenement->getDateDebut());
        $theme = $this->themeForEvent($evenement);

        return $this->normalizePack([
            'poster' => [
                'title' => (string) $evenement->getTitre(),
                'subtitle' => $this->buildSubtitle($evenement, $durationMinutes),
                'date_label' => $evenement->getDateDebut() ? $evenement->getDateDebut()->format('d/m/Y H:i') : 'Date a definir',
                'mode_label' => ucfirst((string) $evenement->getMode()),
                'lieu_label' => $evenement->getLieu() ?: (($evenement->getMode() === 'en_ligne') ? 'Session en ligne' : 'Lieu a confirmer'),
                'theme' => $theme,
            ],
            'cards' => $phasesWithTiming,
            'highlights' => $this->buildHighlights($evenement, $durationMinutes, count($phasesWithTiming)),
        ], $evenement);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildExperiencePackFromExternalApi(Evenement $evenement): ?array
    {
        if ($this->httpClient === null) {
            return null;
        }

        $apiUrl = trim((string) ($_ENV['EXPERIENCE_AI_API_URL'] ?? $_SERVER['EXPERIENCE_AI_API_URL'] ?? 'https://text.pollinations.ai'));
        if ($apiUrl === '') {
            return null;
        }
        $payload = [
            'title' => (string) $evenement->getTitre(),
            'description' => (string) $evenement->getDescription(),
            'type' => (string) $evenement->getType(),
            'mode' => (string) $evenement->getMode(),
            'date_debut' => $evenement->getDateDebut()?->format(DATE_ATOM),
            'date_fin' => $evenement->getDateFin()?->format(DATE_ATOM),
            'lieu' => (string) $evenement->getLieu(),
            'places_max' => $evenement->getPlacesMax(),
            'duration_minutes' => $this->computeDurationMinutes($evenement),
        ];

        try {
            $prompt = $this->buildFreeApiPrompt($payload);
            $response = $this->httpClient->request('GET', rtrim($apiUrl, '/') . '/' . rawurlencode($prompt), [
                'headers' => ['Accept' => 'application/json,text/plain'],
                'timeout' => 10,
            ]);

            $raw = trim($response->getContent(false));
            if ($raw === '') {
                return null;
            }

            $pack = $this->extractPackFromFreeApiResponse($raw);
            if ($pack === null) {
                return null;
            }

            return $pack;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildFreeApiPrompt(array $payload): string
    {
        return 'Retourne UNIQUEMENT un objet JSON valide avec les cles: poster, cards, highlights. '
            . 'poster doit contenir: title, subtitle, date_label, mode_label, lieu_label, theme(bg,chip). '
            . 'cards doit etre une liste d objets {title, icon, duration, time_label, intensity}. '
            . 'highlights doit etre une liste de chaines. '
            . 'Aucun markdown, aucun commentaire. '
            . 'Evenement: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPackFromFreeApiResponse(string $raw): ?array
    {
        $json = trim($raw);
        if (str_contains($json, '```')) {
            $json = preg_replace('/```(?:json)?/i', '', $json) ?? $json;
            $json = str_replace('```', '', $json);
            $json = trim($json);
        }

        $pack = json_decode($json, true);
        if (!is_array($pack)) {
            if (preg_match('/\{[\s\S]*\}/', $json, $m) === 1) {
                $pack = json_decode((string) $m[0], true);
            }
        }

        if (!is_array($pack)) {
            return null;
        }

        if (!isset($pack['poster'], $pack['cards'], $pack['highlights'])) {
            return null;
        }
        if (!is_array($pack['poster']) || !is_array($pack['cards']) || !is_array($pack['highlights'])) {
            return null;
        }
        if (count($pack['cards']) < 3 || count($pack['highlights']) < 2) {
            return null;
        }

        return $pack;
    }

    private function computeDurationMinutes(Evenement $evenement): int
    {
        $start = $evenement->getDateDebut();
        $end = $evenement->getDateFin();
        if (!$start || !$end || $end <= $start) {
            return 120;
        }

        $seconds = $end->getTimestamp() - $start->getTimestamp();
        $minutes = (int) floor($seconds / 60);
        return max(45, min(600, $minutes));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractPhasesFromDescription(string $description): array
    {
        $description = trim($description);
        if ($description === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $description) ?: [];
        $phases = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $isBullet = preg_match('/^[-*•]\s+/u', $line) === 1 || preg_match('/^\d+[\).\-\s]+/u', $line) === 1;
            if (!$isBullet) {
                continue;
            }

            $clean = preg_replace('/^[-*•]\s+/u', '', $line) ?? $line;
            $clean = preg_replace('/^\d+[\).\-\s]+/u', '', $clean) ?? $clean;
            $clean = trim($clean);
            if ($clean === '') {
                continue;
            }

            $durationHint = null;
            if (preg_match('/\((\d{1,3})\s*min\)\s*$/i', $clean, $match) === 1) {
                $durationHint = (int) $match[1];
                $clean = trim((string) preg_replace('/\((\d{1,3})\s*min\)\s*$/i', '', $clean));
            }

            $phases[] = [
                'title' => $clean,
                'icon' => $this->iconForTitle($clean),
                'intensity' => $this->intensityForTitle($clean),
                'duration_hint' => $durationHint,
            ];
        }

        return $phases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultPhasesByType(string $type): array
    {
        $type = mb_strtolower(trim($type));
        return match ($type) {
            'atelier' => [
                ['title' => 'Accueil et cadrage', 'icon' => 'fa-door-open', 'intensity' => 2],
                ['title' => 'Demonstration guidee', 'icon' => 'fa-chalkboard-teacher', 'intensity' => 3],
                ['title' => 'Travail en sous-groupes', 'icon' => 'fa-users-cog', 'intensity' => 4],
                ['title' => 'Restitution et feedback', 'icon' => 'fa-comments', 'intensity' => 3],
                ['title' => 'Synthese finale', 'icon' => 'fa-flag-checkered', 'intensity' => 2],
            ],
            'webinaire' => [
                ['title' => 'Ouverture digitale', 'icon' => 'fa-play-circle', 'intensity' => 2],
                ['title' => 'Intervention principale', 'icon' => 'fa-microphone', 'intensity' => 4],
                ['title' => 'Cas pratiques', 'icon' => 'fa-laptop-medical', 'intensity' => 3],
                ['title' => 'Q&A live', 'icon' => 'fa-question-circle', 'intensity' => 3],
                ['title' => 'Cloture', 'icon' => 'fa-check-circle', 'intensity' => 2],
            ],
            default => [
                ['title' => 'Ouverture', 'icon' => 'fa-flag', 'intensity' => 2],
                ['title' => 'Session 1', 'icon' => 'fa-microphone', 'intensity' => 4],
                ['title' => 'Pause networking', 'icon' => 'fa-coffee', 'intensity' => 1],
                ['title' => 'Session 2', 'icon' => 'fa-chalkboard', 'intensity' => 4],
                ['title' => 'Invite special', 'icon' => 'fa-star', 'intensity' => 3],
                ['title' => 'Cloture', 'icon' => 'fa-check-circle', 'intensity' => 2],
            ],
        };
    }

    /**
     * @param list<array<string, mixed>> $phases
     * @return list<array<string, mixed>>
     */
    private function allocateTiming(array $phases, int $totalMinutes, ?\DateTimeInterface $start): array
    {
        $hasExplicitDurations = count(array_filter($phases, static function (array $phase): bool {
            $v = $phase['duration_hint'] ?? null;
            return is_int($v) && $v > 0;
        })) >= max(1, (int) ceil(count($phases) * 0.6));

        $weights = array_map(static fn (array $p): int => max(1, (int) ($p['intensity'] ?? 3)), $phases);
        $weightSum = array_sum($weights);
        $result = [];
        $cursor = $start ? \DateTimeImmutable::createFromInterface($start) : null;

        foreach ($phases as $index => $phase) {
            if ($hasExplicitDurations && is_int($phase['duration_hint'] ?? null) && (int) $phase['duration_hint'] > 0) {
                $duration = max(10, min(180, (int) $phase['duration_hint']));
            } else {
                $duration = (int) round(($weights[$index] / max(1, $weightSum)) * $totalMinutes);
                $duration = max(10, min(90, $duration));
            }

            $timeLabel = null;
            if ($cursor instanceof \DateTimeImmutable) {
                $phaseEnd = $cursor->modify(sprintf('+%d minutes', $duration));
                $timeLabel = $cursor->format('H:i') . ' - ' . $phaseEnd->format('H:i');
                $cursor = $phaseEnd;
            }

            $result[] = [
                'title' => (string) $phase['title'],
                'icon' => (string) ($phase['icon'] ?? 'fa-circle'),
                'duration' => $duration,
                'time_label' => $timeLabel ?: sprintf('~ %d min', $duration),
                'intensity' => (int) ($phase['intensity'] ?? 3),
            ];
        }

        return $result;
    }

    /**
     * @return array{bg: string, chip: string}
     */
    private function themeForEvent(Evenement $evenement): array
    {
        $key = mb_strtolower((string) $evenement->getType());
        return match ($key) {
            'atelier' => ['bg' => 'linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%)', 'chip' => '#dbeafe'],
            'webinaire' => ['bg' => 'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)', 'chip' => '#ccfbf1'],
            'depistage' => ['bg' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)', 'chip' => '#ffedd5'],
            default => ['bg' => 'linear-gradient(135deg, #6366f1 0%, #7c3aed 100%)', 'chip' => '#ede9fe'],
        };
    }

    private function buildSubtitle(Evenement $evenement, int $durationMinutes): string
    {
        $mode = (string) $evenement->getMode();
        $modeText = match ($mode) {
            'en_ligne' => 'Experience digitale',
            'hybride' => 'Experience hybride',
            default => 'Experience presentielle',
        };

        return sprintf('Programme officiel | %s | %d min', $modeText, $durationMinutes);
    }

    /**
     * @return list<string>
     */
    private function buildHighlights(Evenement $evenement, int $durationMinutes, int $phases): array
    {
        $parts = [];
        $parts[] = sprintf('%d phases structurees', $phases);
        $parts[] = sprintf('Duree totale %d min', $durationMinutes);
        $parts[] = $evenement->getMode() === 'hybride' ? 'Flux pense pour presentiel + en ligne' : 'Flux pense pour une execution fluide';
        return $parts;
    }

    private function iconForTitle(string $title): string
    {
        $t = mb_strtolower($title);
        if (str_contains($t, 'pause') || str_contains($t, 'coffee')) return 'fa-coffee';
        if (str_contains($t, 'atelier') || str_contains($t, 'workshop')) return 'fa-tools';
        if (str_contains($t, 'q&a') || str_contains($t, 'question')) return 'fa-question-circle';
        if (str_contains($t, 'invit') || str_contains($t, 'guest')) return 'fa-star';
        if (str_contains($t, 'ouverture') || str_contains($t, 'accueil')) return 'fa-door-open';
        if (str_contains($t, 'cloture') || str_contains($t, 'closing')) return 'fa-check-circle';
        return 'fa-microphone';
    }

    private function intensityForTitle(string $title): int
    {
        $t = mb_strtolower($title);
        if (str_contains($t, 'pause')) return 1;
        if (str_contains($t, 'q&a') || str_contains($t, 'question')) return 3;
        if (str_contains($t, 'atelier') || str_contains($t, 'workshop')) return 4;
        return 3;
    }

    /**
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    private function normalizePack(array $pack, Evenement $evenement): array
    {
        $duration = $this->computeDurationMinutes($evenement);
        $theme = $this->themeForEvent($evenement);

        $poster = is_array($pack['poster'] ?? null) ? $pack['poster'] : [];
        $cards = is_array($pack['cards'] ?? null) ? $pack['cards'] : [];
        $highlights = is_array($pack['highlights'] ?? null) ? $pack['highlights'] : [];

        $normalizedCards = [];
        foreach ($cards as $index => $card) {
            if (!is_array($card)) {
                continue;
            }

            $title = trim((string) ($card['title'] ?? 'Phase ' . ($index + 1)));
            if ($title === '') {
                $title = 'Phase ' . ($index + 1);
            }

            $durationMin = (int) ($card['duration'] ?? 0);
            if ($durationMin <= 0) {
                $durationMin = max(10, (int) round($duration / max(1, count($cards))));
            }

            $icon = trim((string) ($card['icon'] ?? 'fa-microphone'));
            if ($icon === '') {
                $icon = $this->iconForTitle($title);
            }
            if (!str_starts_with($icon, 'fa-')) {
                $icon = 'fa-' . ltrim($icon, '-');
            }

            $timeLabel = trim((string) ($card['time_label'] ?? ''));
            if ($timeLabel === '') {
                $timeLabel = '~ ' . $durationMin . ' min';
            }

            $normalizedCards[] = [
                'title' => $title,
                'icon' => $icon,
                'duration' => max(10, min(180, $durationMin)),
                'time_label' => $timeLabel,
                'intensity' => max(1, min(5, (int) ($card['intensity'] ?? 3))),
            ];
        }

        if (count($normalizedCards) === 0) {
            $phases = $this->defaultPhasesByType((string) $evenement->getType());
            $normalizedCards = $this->allocateTiming($phases, $duration, $evenement->getDateDebut());
        }

        $normalizedHighlights = [];
        foreach ($highlights as $highlight) {
            $text = trim((string) $highlight);
            if ($text !== '') {
                $normalizedHighlights[] = $text;
            }
        }
        if (count($normalizedHighlights) === 0) {
            $normalizedHighlights = $this->buildHighlights($evenement, $duration, count($normalizedCards));
        }
        $normalizedHighlights = array_slice($normalizedHighlights, 0, 6);

        $posterTheme = is_array($poster['theme'] ?? null) ? $poster['theme'] : [];
        $posterThemeBg = trim($this->toSafeText($posterTheme['bg'] ?? $theme['bg']));
        $posterThemeChip = trim($this->toSafeText($posterTheme['chip'] ?? $theme['chip']));
        if ($posterThemeBg === '') {
            $posterThemeBg = $theme['bg'];
        }
        if ($posterThemeChip === '') {
            $posterThemeChip = $theme['chip'];
        }

        $title = trim($this->toSafeText($poster['title'] ?? $evenement->getTitre() ?? 'Evenement SAHTY'));
        if ($title === '') {
            $title = 'Evenement SAHTY';
        }

        return [
            'poster' => [
                'title' => $title,
                'subtitle' => trim($this->toSafeText($poster['subtitle'] ?? $this->buildSubtitle($evenement, $duration))),
                'date_label' => trim($this->toSafeText($poster['date_label'] ?? ($evenement->getDateDebut() ? $evenement->getDateDebut()->format('d/m/Y H:i') : 'Date a definir'))),
                'mode_label' => trim($this->toSafeText($poster['mode_label'] ?? ucfirst((string) $evenement->getMode()))),
                'lieu_label' => trim($this->toSafeText($poster['lieu_label'] ?? ($evenement->getLieu() ?: (($evenement->getMode() === 'en_ligne') ? 'Session en ligne' : 'Lieu a confirmer')))),
                'theme' => [
                    'bg' => $posterThemeBg,
                    'chip' => $posterThemeChip,
                ],
            ],
            'cards' => $normalizedCards,
            'highlights' => $normalizedHighlights,
        ];
    }

    private function toSafeText(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }
}
