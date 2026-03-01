<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;

class EvenementPlanningModelService
{
    private string $projectDir;
    private string $pythonBin;

    public function __construct(string $projectDir, string $pythonBin = 'python')
    {
        $this->projectDir = rtrim($projectDir, DIRECTORY_SEPARATOR);
        $this->pythonBin = trim($pythonBin) !== '' ? trim($pythonBin) : 'python';
    }

    /**
     * @param array<string, mixed> $features
     * @return array<string, mixed>
     */
    public function predict(array $features): array
    {
        $payload = $this->normalizePayload($features);
        $scriptPath = $this->projectDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'IA_evenement' . DIRECTORY_SEPARATOR . 'predict_planning.py';

        if (!is_file($scriptPath)) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Script IA introuvable, fallback applique.'),
            ];
        }

        $python = $this->resolvePythonBinary();
        if ($python === null) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Python indisponible, fallback applique.'),
            ];
        }

        $jsonInput = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonInput === false) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Payload invalide, fallback applique.'),
            ];
        }

        try {
            $process = new Process([$python, $scriptPath, $jsonInput], $this->projectDir);
            $process->setTimeout(45);
            $process->run();
        } catch (\Throwable) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Execution IA indisponible, fallback applique.'),
            ];
        }

        if (!$process->isSuccessful()) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Modele IA indisponible, fallback applique.'),
            ];
        }

        $decoded = json_decode((string) $process->getOutput(), true);
        if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Reponse IA invalide, fallback applique.'),
            ];
        }

        $phases = [];
        foreach ((array) ($decoded['phases'] ?? []) as $phase) {
            if (!is_array($phase)) {
                continue;
            }
            $phases[] = [
                'ordre' => max(1, (int) ($phase['ordre'] ?? count($phases) + 1)),
                'phase_label' => (string) ($phase['phase_label'] ?? 'phase'),
                'duree_min' => max(8, (int) ($phase['duree_min'] ?? 15)),
            ];
        }

        if ($phases === []) {
            return [
                'ready' => true,
                'recommendation' => $this->buildFallbackRecommendation($payload, 'Aucune phase IA, fallback applique.'),
            ];
        }

        $confidence = null;
        if (isset($decoded['confidence']) && is_numeric($decoded['confidence'])) {
            $confidence = (float) $decoded['confidence'];
        }

        $recommendation = [
            'phases' => $phases,
            'confidence' => $confidence,
        ];

        if (isset($decoded['warning']) && is_string($decoded['warning']) && trim($decoded['warning']) !== '') {
            $recommendation['warning'] = trim($decoded['warning']);
        }

        return [
            'ready' => true,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * @param array<string, mixed> $features
     * @return array<string, mixed>
     */
    private function normalizePayload(array $features): array
    {
        $duration = (int) ($features['duration_total_min'] ?? 180);
        $duration = max(60, min(720, $duration));

        return [
            'event_type' => $this->sanitizeSlug((string) ($features['event_type'] ?? 'formation'), 'formation'),
            'mode' => $this->sanitizeSlug((string) ($features['mode'] ?? 'presentiel'), 'presentiel'),
            'audience' => $this->sanitizeSlug((string) ($features['audience'] ?? 'mixte'), 'mixte'),
            'level' => $this->sanitizeSlug((string) ($features['level'] ?? 'intermediaire'), 'intermediaire'),
            'duration_total_min' => $duration,
        ];
    }

    private function sanitizeSlug(string $value, string $fallback): string
    {
        $value = trim(strtolower($value));
        return $value !== '' ? $value : $fallback;
    }

    private function resolvePythonBinary(): ?string
    {
        $candidates = [$this->pythonBin];

        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            $localAppData = $_SERVER['LOCALAPPDATA'] ?? getenv('LOCALAPPDATA') ?: null;
            $userProfile = $_SERVER['USERPROFILE'] ?? getenv('USERPROFILE') ?: null;

            if (is_string($localAppData) && $localAppData !== '') {
                $candidates[] = $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python313' . DIRECTORY_SEPARATOR . 'python.exe';
                $candidates[] = $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python312' . DIRECTORY_SEPARATOR . 'python.exe';
                $candidates[] = $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python311' . DIRECTORY_SEPARATOR . 'python.exe';
            }

            if (is_string($userProfile) && $userProfile !== '') {
                $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python313' . DIRECTORY_SEPARATOR . 'python.exe';
                $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python312' . DIRECTORY_SEPARATOR . 'python.exe';
                $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python311' . DIRECTORY_SEPARATOR . 'python.exe';
                $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'WindowsApps' . DIRECTORY_SEPARATOR . 'python.exe';
            }

            $candidates[] = 'py';
            $candidates[] = 'python';
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                $probe = stripos(PHP_OS_FAMILY, 'Windows') === 0 && strtolower($candidate) === 'py'
                    ? new Process([$candidate, '-3', '--version'], $this->projectDir)
                    : new Process([$candidate, '--version'], $this->projectDir);

                $probe->setTimeout(5);
                $probe->run();

                if ($probe->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildFallbackRecommendation(array $payload, string $warning): array
    {
        $duration = (int) ($payload['duration_total_min'] ?? 180);
        $base = [
            ['phase_label' => 'ouverture', 'ratio' => 0.12],
            ['phase_label' => 'intervention', 'ratio' => 0.30],
            ['phase_label' => 'pause', 'ratio' => 0.10],
            ['phase_label' => 'atelier', 'ratio' => 0.33],
            ['phase_label' => 'cloture', 'ratio' => 0.15],
        ];

        $allocated = [];
        $sum = 0;
        foreach ($base as $index => $item) {
            $minutes = max(8, (int) round($duration * $item['ratio']));
            $sum += $minutes;
            $allocated[] = [
                'ordre' => $index + 1,
                'phase_label' => $item['phase_label'],
                'duree_min' => $minutes,
            ];
        }

        $delta = $duration - $sum;
        $cursor = 0;
        while ($delta !== 0 && $cursor < 5000) {
            $idx = $cursor % count($allocated);
            if ($delta > 0) {
                $allocated[$idx]['duree_min']++;
                $delta--;
            } elseif ($allocated[$idx]['duree_min'] > 8) {
                $allocated[$idx]['duree_min']--;
                $delta++;
            }
            $cursor++;
        }

        return [
            'phases' => $allocated,
            'confidence' => null,
            'warning' => $warning,
        ];
    }
}

