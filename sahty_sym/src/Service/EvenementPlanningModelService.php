<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class EvenementPlanningModelService
{
    private string $iaDir;

    public function __construct(
        private readonly string $projectDir,
        private readonly string $pythonBin = 'python'
    ) {
        $this->iaDir = $this->projectDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'IA_evenement';
    }

    public function predict(array $payload): array
    {
        $predictScript = $this->iaDir . DIRECTORY_SEPARATOR . 'predict_planning.py';
        $modelPath = $this->iaDir . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'planning_model.joblib';

        if (!is_file($predictScript)) {
            return [
                'ready' => false,
                'message' => 'Script de prediction IA introuvable. Verifiez src/IA_evenement/predict_planning.py.',
            ];
        }

        if (!is_file($modelPath)) {
            return [
                'ready' => false,
                'message' => 'Modele non entraine. Lancez: python src/IA_evenement/train_model.py --dataset src/IA_evenement/event_planning_dataset.csv --out src/IA_evenement/models',
            ];
        }

        $input = [
            'event_type' => (string) ($payload['event_type'] ?? 'formation'),
            'mode' => (string) ($payload['mode'] ?? 'presentiel'),
            'audience' => (string) ($payload['audience'] ?? 'mixte'),
            'level' => (string) ($payload['level'] ?? 'intermediaire'),
            'duration_total_min' => (int) ($payload['duration_total_min'] ?? 180),
        ];

        $process = new Process([
            $this->pythonBin,
            $predictScript,
            json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ], $this->iaDir);

        $process->setTimeout(12);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'ready' => false,
                'message' => 'Erreur execution IA: ' . trim($process->getErrorOutput() ?: $process->getOutput()),
            ];
        }

        $raw = trim($process->getOutput());
        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || empty($decoded['ok'])) {
            return [
                'ready' => false,
                'message' => is_array($decoded) && isset($decoded['message'])
                    ? (string) $decoded['message']
                    : 'Reponse IA invalide.',
            ];
        }

        return [
            'ready' => true,
            'recommendation' => $decoded,
        ];
    }
}
