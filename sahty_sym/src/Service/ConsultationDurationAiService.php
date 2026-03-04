<?php

namespace App\Service;

use App\Entity\FicheMedicale;
use App\Entity\RendezVous;
use Symfony\Component\HttpKernel\KernelInterface;

class ConsultationDurationAiService
{
    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->projectDir = $kernel->getProjectDir();
    }

    public function predict(RendezVous $rdv, ?FicheMedicale $fiche = null): ?float
    {
        if ($fiche === null) {
            return $this->fallbackPrediction($rdv, null);
        }

        $python = $this->resolvePythonBinary();
        if ($python === null) {
            return $this->fallbackPrediction($rdv, $fiche);
        }

        $script = $this->projectDir . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'consultation_duration_ml.py';
        $data = $this->projectDir . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'consultation_dataset_utf8.csv';

        if (!is_file($script) || !is_file($data)) {
            return $this->fallbackPrediction($rdv, $fiche);
        }

        $motif = $rdv->getRaison() ?: 'controle';
        $antecedents = $fiche->getAntecedents() ?: 'aucun';
        $allergies = $fiche->getAllergies() ?: 'aucune';
        $traitement = $fiche->getTraitementEnCours() ?: 'rien';
        $taille = $fiche->getTaille() ?: '1.70';
        $poids = $fiche->getPoids() ?: '70';
        $imc = $fiche->getImc() ? (string) $fiche->getImc() : '';
        $categorieImc = $fiche->getCategorieImc() ?: '';

        $command = implode(' ', [
            escapeshellarg($python),
            escapeshellarg($script),
            '--data',
            escapeshellarg($data),
            '--predict',
            '--motif',
            escapeshellarg((string) $motif),
            '--antecedents',
            escapeshellarg((string) $antecedents),
            '--allergies',
            escapeshellarg((string) $allergies),
            '--traitement_en_cours',
            escapeshellarg((string) $traitement),
            '--taille',
            escapeshellarg((string) $taille),
            '--poids',
            escapeshellarg((string) $poids),
            '--imc',
            escapeshellarg((string) $imc),
            '--categorie_imc',
            escapeshellarg((string) $categorieImc),
        ]);

        $outputLines = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $outputLines, $exitCode);

        if ($exitCode !== 0) {
            return $this->fallbackPrediction($rdv, $fiche);
        }

        $output = implode("\n", $outputLines);
        if (!preg_match('/Duree predite:\s*([0-9]+(?:[\.,][0-9]+)?)/i', $output, $matches)) {
            return $this->fallbackPrediction($rdv, $fiche);
        }

        $rawPrediction = (float) str_replace(',', '.', $matches[1]);
        return $this->adjustPrediction($rawPrediction, $rdv, $fiche);
    }

    private function adjustPrediction(float $rawPrediction, RendezVous $rdv, FicheMedicale $fiche): float
    {
        $motif = mb_strtolower((string) ($rdv->getRaison() ?? ''), 'UTF-8');
        $antecedents = mb_strtolower((string) ($fiche->getAntecedents() ?? ''), 'UTF-8');

        // Baseline to avoid unrealistically short values in production UX.
        $adjusted = max($rawPrediction, 18.0);

        if ($this->containsAny($motif, ['urgence', 'douleur thoracique', 'dyspnee', 'essoufflement'])) {
            $adjusted = max($adjusted, 35.0);
        } elseif ($this->containsAny($motif, ['suivi diabete', 'diabete'])) {
            $adjusted = max($adjusted, 28.0);
        } elseif ($this->containsAny($motif, ['suivi hypertension', 'hypertension', 'hta'])) {
            $adjusted = max($adjusted, 24.0);
        } elseif ($this->containsAny($motif, ['bilan', 'annuel'])) {
            $adjusted = max($adjusted, 22.0);
        } elseif ($this->containsAny($motif, ['infection'])) {
            $adjusted = max($adjusted, 20.0);
        }

        if ($this->containsAny($antecedents, ['diabete', 'hypertension', 'cardio', 'asthme'])) {
            $adjusted += 3.0;
        }

        return min(round($adjusted, 1), 60.0);
    }

    /**
     * @param array<int, string> $keywords
     */
    private function containsAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_strpos($text, $keyword, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    private function fallbackPrediction(RendezVous $rdv, ?FicheMedicale $fiche): float
    {
        $motif = mb_strtolower((string) ($rdv->getRaison() ?? ''), 'UTF-8');
        $minutes = 22.0;

        if ($this->containsAny($motif, ['urgence', 'douleur thoracique', 'dyspnee', 'essoufflement'])) {
            $minutes = 40.0;
        } elseif ($this->containsAny($motif, ['suivi diabete', 'diabete'])) {
            $minutes = 30.0;
        } elseif ($this->containsAny($motif, ['suivi hypertension', 'hypertension', 'hta'])) {
            $minutes = 26.0;
        } elseif ($this->containsAny($motif, ['infection'])) {
            $minutes = 24.0;
        } elseif ($this->containsAny($motif, ['bilan', 'annuel'])) {
            $minutes = 25.0;
        } elseif ($this->containsAny($motif, ['renouvellement', 'ordonnance'])) {
            $minutes = 18.0;
        }

        $motifWordCount = count(array_filter(explode(' ', trim($motif))));
        if ($motifWordCount >= 5) {
            $minutes += 2.0;
        }
        if ($motifWordCount >= 9) {
            $minutes += 2.0;
        }

        if ($rdv->getTypeConsultation() === 'en_ligne') {
            $minutes -= 2.0;
        }

        if ($fiche !== null) {
            $antecedents = trim((string) ($fiche->getAntecedents() ?? ''));
            $allergies = trim((string) ($fiche->getAllergies() ?? ''));
            $traitement = trim((string) ($fiche->getTraitementEnCours() ?? ''));

            if ($antecedents !== '') {
                $minutes += 3.0;
            }
            if ($allergies !== '') {
                $minutes += 1.0;
            }
            if ($traitement !== '' && strtolower($traitement) !== 'rien') {
                $minutes += 2.0;
            }

            $categorieImc = mb_strtolower((string) ($fiche->getCategorieImc() ?? ''), 'UTF-8');
            if ($this->containsAny($categorieImc, ['ob', 'surpoids'])) {
                $minutes += 2.0;
            }
        }

        $minutes = max(15.0, min(60.0, $minutes));
        return round($minutes, 1);
    }

    private function resolvePythonBinary(): ?string
    {
        $envPython = $_ENV['PYTHON_BIN'] ?? $_SERVER['PYTHON_BIN'] ?? getenv('PYTHON_BIN');
        $candidates = [];

        if (is_string($envPython) && $envPython !== '') {
            $candidates[] = $envPython;
        }

        $localAppData = getenv('LOCALAPPDATA');
        if (is_string($localAppData) && $localAppData !== '') {
            $candidates[] = $localAppData . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python312' . DIRECTORY_SEPARATOR . 'python.exe';
        }

        $userProfile = getenv('USERPROFILE');
        if (is_string($userProfile) && $userProfile !== '') {
            $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python312' . DIRECTORY_SEPARATOR . 'python.exe';
            $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python311' . DIRECTORY_SEPARATOR . 'python.exe';
            $candidates[] = $userProfile . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python310' . DIRECTORY_SEPARATOR . 'python.exe';
        }

        $candidates[] = 'python';
        $candidates[] = 'py';

        foreach ($candidates as $candidate) {
            $tmp = [];
            $code = 1;
            exec(escapeshellarg($candidate) . ' --version 2>&1', $tmp, $code);
            if ($code === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
