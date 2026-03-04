<?php

namespace App\Service;

class EvenementPlanningModelService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $pythonBin = 'python'
    ) {
    }

    /**
     * Return a model-like recommendation payload expected by the frontend.
     *
     * @param array<string, mixed> $features
     * @return array<string, mixed>
     */
    public function predict(array $features): array
    {
        $duration = (int) ($features['duration_total_min'] ?? 180);
        $duration = max(60, min(720, $duration));

        $type = $this->normalize((string) ($features['event_type'] ?? 'formation'));
        $mode = $this->normalize((string) ($features['mode'] ?? 'presentiel'));
        $level = $this->normalize((string) ($features['level'] ?? 'intermediaire'));

        $phases = $this->buildPhases($duration, $type, $mode, $level);

        return [
            'ready' => true,
            'recommendation' => [
                'confidence' => 0.78,
                'phases' => $phases,
            ],
            'meta' => [
                'engine' => 'local_rule_model',
                'project_dir' => $this->projectDir,
                'python_bin' => $this->pythonBin,
            ],
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function buildPhases(int $duration, string $type, string $mode, string $level): array
    {
        $slots = $this->splitDuration($duration);

        $labels = [
            'ouverture',
            'orientation',
            'atelier_pratique',
            'pause',
            'q_a',
            'cloture',
        ];

        if ($type === 'conference') {
            $labels = ['ouverture', 'keynote', 'table_ronde', 'pause', 'q_a', 'cloture'];
        } elseif ($type === 'depistage') {
            $labels = ['ouverture', 'briefing', 'flux_depistage', 'pause', 'orientation', 'cloture'];
        }

        if ($mode === 'en_ligne') {
            $labels[2] = 'session_interactive';
        }

        if ($level === 'debutant') {
            $labels[2] = 'atelier_guide';
        } elseif ($level === 'avance') {
            $labels[2] = 'atelier_cas_complexes';
        }

        $phases = [];
        foreach ($slots as $index => $minutes) {
            $phases[] = [
                'ordre' => $index + 1,
                'phase_label' => $labels[$index] ?? ('phase_' . ($index + 1)),
                'duree_min' => $minutes,
            ];
        }

        return $phases;
    }

    /**
     * @return int[]
     */
    private function splitDuration(int $duration): array
    {
        // 6 phases target: opening, orientation, core, break, Q&A, closing
        $ratios = [0.12, 0.14, 0.34, 0.10, 0.18, 0.12];
        $slots = [];
        $used = 0;

        foreach ($ratios as $idx => $ratio) {
            if ($idx === count($ratios) - 1) {
                $slots[] = max(10, $duration - $used);
                break;
            }
            $minutes = (int) round($duration * $ratio);
            $minutes = max(10, $minutes);
            $slots[] = $minutes;
            $used += $minutes;
        }

        return $slots;
    }

    private function normalize(string $value): string
    {
        $value = trim(strtolower($value));
        return $value === '' ? 'intermediaire' : $value;
    }
}

