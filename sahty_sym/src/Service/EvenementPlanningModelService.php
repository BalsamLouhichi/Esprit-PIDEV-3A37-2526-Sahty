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
        $jsonInput = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonInput === false) {
            return [
                'ready' => false,
                'message' => 'Impossible de serialiser la charge IA.',
            ];
        }

        $primaryBin = trim($this->pythonBin);
        if ($primaryBin !== '' && $this->isAbsolutePath($primaryBin) && !is_file($primaryBin)) {
            return [
                'ready' => false,
                'message' => sprintf(
                    'Executable Python configure introuvable: %s. Mettez a jour IA_EVENEMENT_PYTHON_BIN dans .env.local.',
                    $primaryBin
                ),
            ];
        }

        $lastError = '';
        foreach ($this->resolvePythonCandidates() as $candidate) {
            if ($this->isAbsolutePath($candidate) && !is_file($candidate)) {
                $lastError = sprintf('Executable Python introuvable: %s', $candidate);
                continue;
            }

            $process = new Process(
                [$candidate, $predictScript, $jsonInput],
                $this->iaDir,
                [
                    'PYTHONUTF8' => '1',
                    'PYTHONIOENCODING' => 'utf-8',
                ]
            );
            $process->setTimeout(15);
            $process->run();

            if (!$process->isSuccessful()) {
                $stderr = trim($this->sanitizeUtf8($process->getErrorOutput()));
                $stdout = trim($this->sanitizeUtf8($process->getOutput()));
                $lastError = sprintf('[%s] %s', $candidate, $stderr !== '' ? $stderr : $stdout);
                continue;
            }

            $raw = ltrim(trim($this->sanitizeUtf8($process->getOutput())), "\xEF\xBB\xBF");
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $lastError = sprintf('[%s] Reponse JSON invalide: %s', $candidate, $raw);
                continue;
            }

            if (empty($decoded['ok'])) {
                return [
                    'ready' => false,
                    'message' => isset($decoded['message'])
                        ? (string) $decoded['message']
                        : 'Reponse IA invalide.',
                ];
            }

            return [
                'ready' => true,
                'recommendation' => $this->sanitizeUtf8Array($decoded),
            ];
        }

        return [
            'ready' => false,
            'message' => 'Erreur execution IA: ' . ($lastError !== '' ? $lastError : 'Aucun interpreteur Python utilisable.'),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePythonCandidates(): array
    {
        $candidates = [];
        $primary = trim($this->pythonBin);
        if ($primary !== '') {
            $candidates[] = $primary;
        }

        $fromEnv = trim((string) ($this->readEnvVar('IA_EVENEMENT_PYTHON_BIN') ?? ''));
        if ($fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        $fromMlEnv = trim((string) ($this->readEnvVar('ML_PYTHON_BIN') ?? ''));
        if ($fromMlEnv !== '') {
            $candidates[] = $fromMlEnv;
        }

        $autoDiscovered = $this->discoverWindowsPython();
        if ($autoDiscovered !== null) {
            $candidates[] = $autoDiscovered;
        }

        $candidates[] = 'python';

        $unique = [];
        foreach ($candidates as $candidate) {
            if (!in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }
        }

        return $unique;
    }

    private function readEnvVar(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function discoverWindowsPython(): ?string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return null;
        }

        $userProfile = (string) ($this->readEnvVar('USERPROFILE') ?? '');
        if ($userProfile === '') {
            return null;
        }

        $pattern = rtrim(str_replace('/', '\\', $userProfile), '\\') . '\\AppData\\Local\\Programs\\Python\\Python*\\python.exe';
        $matches = glob($pattern);
        if (!is_array($matches) || $matches === []) {
            return null;
        }

        rsort($matches, SORT_NATURAL);
        foreach ($matches as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/');
    }

    private function sanitizeUtf8(string $value): string
    {
        if ($value === '' || preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        if (function_exists('mb_convert_encoding')) {
            return (string) @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        return utf8_encode($value);
    }

    private function sanitizeUtf8Array(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->sanitizeUtf8($value);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeUtf8Array($value);
            }
        }

        return $data;
    }
}
