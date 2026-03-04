<?php

namespace App\Controller;

use App\Service\MlDatasetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;

#[Route('/admin', name: 'admin_')]
class AdminPredictionController extends AbstractController
{
    #[Route('/predictions', name: 'predictions', methods: ['GET'])]
    public function index(KernelInterface $kernel): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $projectDir = rtrim($kernel->getProjectDir(), DIRECTORY_SEPARATOR);
        $mlDir = $projectDir . DIRECTORY_SEPARATOR . 'ml';

        $datasetPath = $mlDir . DIRECTORY_SEPARATOR . 'dataset.csv';
        $churnPath = $mlDir . DIRECTORY_SEPARATOR . 'predictions_churn.csv';
        $fraudPath = $mlDir . DIRECTORY_SEPARATOR . 'predictions_fraud.csv';

        $datasetRows = $this->readCsv($datasetPath);
        $churnRows = $this->readCsv($churnPath);
        $fraudRows = $this->readCsv($fraudPath);

        $predictions = $this->buildPredictionRows($datasetRows, $churnRows, $fraudRows);
        $meta = [
            'dataset_updated' => $this->fileUpdatedAt($datasetPath),
            'churn_updated' => $this->fileUpdatedAt($churnPath),
            'fraud_updated' => $this->fileUpdatedAt($fraudPath),
        ];

        return $this->render('admin/predictions/index.html.twig', [
            'predictions' => $predictions,
            'meta' => $meta,
        ]);
    }

    #[Route('/predictions/recalculate', name: 'predictions_recalculate', methods: ['POST'])]
    public function recalculate(Request $request, KernelInterface $kernel, MlDatasetService $datasetService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('recalc_predictions', (string) (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('admin_predictions');
        }

        $projectDir = rtrim($kernel->getProjectDir(), DIRECTORY_SEPARATOR);
        $mlDir = $projectDir . DIRECTORY_SEPARATOR . 'ml';

        $scriptPath = $mlDir . DIRECTORY_SEPARATOR . 'fraud_user_ml.py';
        $datasetPath = $mlDir . DIRECTORY_SEPARATOR . 'dataset.csv';

        if (!is_file($scriptPath)) {
            $this->addFlash('error', 'Script ML introuvable: ' . $scriptPath);
            return $this->redirectToRoute('admin_predictions');
        }

        try {
            $rows = $datasetService->buildDataset($datasetPath);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Echec creation dataset ML: ' . $e->getMessage());
            return $this->redirectToRoute('admin_predictions');
        }

        $pythonBin = $this->resolvePythonBin();
        $process = new Process([$pythonBin, $scriptPath, '--data', $datasetPath], $projectDir);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            $error = $error !== '' ? $error : 'Echec execution du script ML.';
            $this->addFlash('error', $error);
            return $this->redirectToRoute('admin_predictions');
        }

        $this->addFlash('success', sprintf('Predictions ML recalculées avec succes (%d lignes).', $rows));
        return $this->redirectToRoute('admin_predictions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }

        $headers = array_map(static fn ($h) => trim((string) $h), $headers);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $key) {
                $row[$key] = $this->castCsvValue($data[$i] ?? null);
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    private function castCsvValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            if (is_numeric($value)) {
                return str_contains($value, '.') ? (float) $value : (int) $value;
            }
        }

        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $datasetRows
     * @param array<int, array<string, mixed>> $churnRows
     * @param array<int, array<string, mixed>> $fraudRows
     * @return array<int, array<string, mixed>>
     */
    private function buildPredictionRows(array $datasetRows, array $churnRows, array $fraudRows): array
    {
        $datasetById = [];
        foreach ($datasetRows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id > 0) {
                $datasetById[$id] = $row;
            }
        }

        $churnById = [];
        foreach ($churnRows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id > 0) {
                $churnById[$id] = $row;
            }
        }

        $fraudById = [];
        foreach ($fraudRows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id > 0) {
                $fraudById[$id] = $row;
            }
        }

        $allIds = array_values(array_unique(array_merge(
            array_keys($datasetById),
            array_keys($churnById),
            array_keys($fraudById)
        )));
        sort($allIds);

        $results = [];
        foreach ($allIds as $id) {
            $base = array_merge($this->defaultDatasetRow($id), $datasetById[$id] ?? []);
            $demands = (int) ($base['demands_30d'] ?? 0);
            $appointments = (int) ($base['appointments_30d'] ?? 0);

            $churn = $churnById[$id] ?? [];
            $fraud = $fraudById[$id] ?? [];

            $churnProb = isset($churn['probability']) ? (float) $churn['probability'] : null;
            $fraudProb = isset($fraud['probability']) ? (float) $fraud['probability'] : null;

            $results[] = array_merge($base, [
                'user_id' => $id,
                'activity_30d' => $demands + $appointments,
                'churn_prediction' => isset($churn['prediction']) ? (int) $churn['prediction'] : null,
                'churn_probability' => $churnProb,
                'churn_risk_score' => $churnProb !== null ? round($churnProb * 100, 2) : null,
                'churn_risk_label' => $this->probabilityLabel($churnProb),
                'fraud_prediction' => isset($fraud['prediction']) ? (int) $fraud['prediction'] : null,
                'fraud_probability' => $fraudProb,
                'fraud_risk_score' => $fraudProb !== null ? round($fraudProb * 100, 2) : null,
                'fraud_risk_label' => $this->probabilityLabel($fraudProb),
                'overall_risk' => max($churnProb ?? 0, $fraudProb ?? 0),
            ]);
        }

        usort($results, static function (array $a, array $b): int {
            return ($b['overall_risk'] ?? 0) <=> ($a['overall_risk'] ?? 0);
        });

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultDatasetRow(int $userId): array
    {
        return [
            'user_id' => $userId,
            'user_name' => null,
            'role' => null,
            'region' => null,
            'device_type' => null,
            'account_age_days' => null,
            'last_activity_days' => null,
            'demands_30d' => 0,
            'appointments_30d' => 0,
            'cancellations_30d' => 0,
            'cancel_rate_30d' => null,
            'abnormal_night_actions_30d' => 0,
            'shared_phone_count' => 0,
            'payment_failures_30d' => 0,
            'chargebacks_30d' => 0,
            'avg_payment_amount_30d' => null,
            'is_fraud' => null,
            'is_churn' => null,
        ];
    }

    private function probabilityLabel(?float $probability): string
    {
        if ($probability === null) {
            return 'faible';
        }
        if ($probability >= 0.7) {
            return 'eleve';
        }
        if ($probability >= 0.4) {
            return 'moyen';
        }
        return 'faible';
    }

    private function fileUpdatedAt(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $timestamp = filemtime($path);
        if ($timestamp === false) {
            return null;
        }
        return (new \DateTimeImmutable())->setTimestamp($timestamp)->format('Y-m-d H:i');
    }

    private function resolvePythonBin(): string
    {
        $env = $_ENV['ML_PYTHON_BIN'] ?? $_SERVER['ML_PYTHON_BIN'] ?? null;
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return 'python';
    }
}




