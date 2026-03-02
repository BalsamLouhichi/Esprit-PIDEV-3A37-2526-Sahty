<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class MlDatasetService
{
    private const CANCEL_STATUSES = [
        'Annule',
        'annule',
        'Annulé',
        'annulé',
        'Annulee',
        'annulee',
        'Annulée',
        'annulée',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function buildDataset(string $path): int
    {
        $now = new \DateTimeImmutable();
        $since30 = $now->sub(new \DateInterval('P30D'));

        $users = $this->em->createQueryBuilder()
            ->select('u.id as id, u.role as role, u.ville as ville, u.telephone as telephone, u.nom as nom, u.prenom as prenom, u.creeLe as createdAt')
            ->from(Utilisateur::class, 'u')
            ->getQuery()
            ->getResult();

        if (!$users) {
            return $this->writeCsv($path, []);
        }

        $phoneCounts = $this->getPhoneCounts();
        $demands30 = $this->countDemandsByPatientSince($since30);
        $appointments30 = $this->countAppointmentsByPatientSince($since30);
        $cancellations30 = $this->countCancellationsByPatientSince($since30);
        $lastDemandDates = $this->getLastDemandDates();
        $lastAppointmentDates = $this->getLastAppointmentDates();
        $abnormalCounts = $this->getAbnormalActivityCounts($since30);

        $rows = [];
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $createdAt = $user['createdAt'] instanceof \DateTimeInterface
                ? $user['createdAt']
                : $now;

            $lastDemand = $lastDemandDates[$userId] ?? null;
            $lastAppointment = $lastAppointmentDates[$userId] ?? null;
            $lastActivity = $this->maxDate($createdAt, $lastDemand, $lastAppointment);

            $accountAgeDays = $this->diffDays($createdAt, $now);
            $lastActivityDays = $lastActivity ? $this->diffDays($lastActivity, $now) : $accountAgeDays;

            $demands = $demands30[$userId] ?? 0;
            $appointments = $appointments30[$userId] ?? 0;
            $cancellations = $cancellations30[$userId] ?? 0;
            $activity30 = $demands + $appointments;

            $cancelRate = $appointments > 0 ? $cancellations / $appointments : 0.0;
            $abnormal = $abnormalCounts[$userId] ?? 0;

            $phone = isset($user['telephone']) ? trim((string) $user['telephone']) : '';
            $sharedCount = 0;
            if ($phone !== '' && isset($phoneCounts[$phone])) {
                $sharedCount = max(0, $phoneCounts[$phone] - 1);
            }

            $paymentFailures = 0;
            $chargebacks = 0;
            $avgPayment = 0.0;

            $churnScore = $this->computeChurnScore($lastActivityDays, $activity30);

            $fraudScore = (1.2 * $chargebacks)
                + (0.8 * $paymentFailures)
                + (0.6 * $sharedCount)
                + (0.4 * $abnormal)
                + (0.3 * ($cancelRate * 10))
                + (0.2 * ($activity30 / 5.0));
            $fraudProb = $this->sigmoid($fraudScore - 3.0);
            $isFraud = $fraudProb >= 0.5 ? 1 : 0;

            $firstName = trim((string) ($user['prenom'] ?? ''));
            $lastName = trim((string) ($user['nom'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);
            if ($fullName === '') {
                $fullName = null;
            }

            $rows[] = [
                'user_id' => $userId,
                'user_name' => $fullName,
                'role' => $user['role'] ?? 'unknown',
                'region' => $user['ville'] ?? 'unknown',
                'device_type' => 'unknown',
                'account_age_days' => $accountAgeDays,
                'last_activity_days' => $lastActivityDays,
                'demands_30d' => $demands,
                'appointments_30d' => $appointments,
                'cancellations_30d' => $cancellations,
                'cancel_rate_30d' => $this->formatFloat($cancelRate, 4),
                'abnormal_night_actions_30d' => $abnormal,
                'shared_phone_count' => $sharedCount,
                'payment_failures_30d' => $paymentFailures,
                'chargebacks_30d' => $chargebacks,
                'avg_payment_amount_30d' => $this->formatFloat($avgPayment, 2),
                'is_fraud' => $isFraud,
                'is_churn' => 0,
                '_churn_score' => $churnScore,
            ];
        }

        $this->assignChurnLabels($rows);

        return $this->writeCsv($path, $rows);
    }

    /**
     * @return array<string, int>
     */
    private function getPhoneCounts(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('u.telephone as telephone, COUNT(u.id) as total')
            ->from(Utilisateur::class, 'u')
            ->where('u.telephone IS NOT NULL')
            ->groupBy('u.telephone')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $phone = trim((string) $row['telephone']);
            if ($phone !== '') {
                $map[$phone] = (int) $row['total'];
            }
        }
        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function countDemandsByPatientSince(\DateTimeInterface $since): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(d.patient) as patientId, COUNT(d.id) as total')
            ->from(DemandeAnalyse::class, 'd')
            ->where('d.date_demande >= :since')
            ->andWhere('d.patient IS NOT NULL')
            ->groupBy('d.patient')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        return $this->rowsToCountMap($rows, 'patientId');
    }

    /**
     * @return array<int, int>
     */
    private function countAppointmentsByPatientSince(\DateTimeInterface $since): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, COUNT(r.id) as total')
            ->from(RendezVous::class, 'r')
            ->where('r.creeLe >= :since')
            ->andWhere('r.patient IS NOT NULL')
            ->groupBy('r.patient')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        return $this->rowsToCountMap($rows, 'patientId');
    }

    /**
     * @return array<int, int>
     */
    private function countCancellationsByPatientSince(\DateTimeInterface $since): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, COUNT(r.id) as total')
            ->from(RendezVous::class, 'r')
            ->where('r.creeLe >= :since')
            ->andWhere('r.statut IN (:cancel)')
            ->andWhere('r.patient IS NOT NULL')
            ->groupBy('r.patient')
            ->setParameter('since', $since)
            ->setParameter('cancel', self::CANCEL_STATUSES)
            ->getQuery()
            ->getResult();

        return $this->rowsToCountMap($rows, 'patientId');
    }

    /**
     * @return array<int, \DateTimeInterface>
     */
    private function getLastDemandDates(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(d.patient) as patientId, MAX(d.date_demande) as lastDate')
            ->from(DemandeAnalyse::class, 'd')
            ->where('d.patient IS NOT NULL')
            ->groupBy('d.patient')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['lastDate'] instanceof \DateTimeInterface) {
                $map[(int) $row['patientId']] = $row['lastDate'];
            }
        }
        return $map;
    }

    /**
     * @return array<int, \DateTimeInterface>
     */
    private function getLastAppointmentDates(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, MAX(r.creeLe) as lastDate')
            ->from(RendezVous::class, 'r')
            ->where('r.patient IS NOT NULL')
            ->groupBy('r.patient')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['lastDate'] instanceof \DateTimeInterface) {
                $map[(int) $row['patientId']] = $row['lastDate'];
            }
        }
        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function getAbnormalActivityCounts(\DateTimeInterface $since): array
    {
        $startHour = 0;
        $endHour = 6;

        $demandRows = $this->em->createQueryBuilder()
            ->select('IDENTITY(d.patient) as patientId, d.date_demande as dateDemande')
            ->from(DemandeAnalyse::class, 'd')
            ->where('d.date_demande >= :since')
            ->andWhere('d.patient IS NOT NULL')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $rdvRows = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, r.creeLe as createdAt')
            ->from(RendezVous::class, 'r')
            ->where('r.creeLe >= :since')
            ->andWhere('r.patient IS NOT NULL')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $demandCounts = $this->countAbnormalFromRows($demandRows, 'patientId', 'dateDemande', $startHour, $endHour);
        $rdvCounts = $this->countAbnormalFromRows($rdvRows, 'patientId', 'createdAt', $startHour, $endHour);

        return $this->mergeCounts($demandCounts, $rdvCounts);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function countAbnormalFromRows(
        array $rows,
        string $idKey,
        string $dateKey,
        int $startHour,
        int $endHour
    ): array {
        $counts = [];
        foreach ($rows as $row) {
            $id = (int) ($row[$idKey] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $date = $row[$dateKey] ?? null;
            if (!$date instanceof \DateTimeInterface) {
                continue;
            }

            $hour = (int) $date->format('G');
            if ($this->isAbnormalHour($hour, $startHour, $endHour)) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function isAbnormalHour(int $hour, int $startHour, int $endHour): bool
    {
        if ($startHour === $endHour) {
            return true;
        }

        if ($startHour < $endHour) {
            return $hour >= $startHour && $hour < $endHour;
        }

        return $hour >= $startHour || $hour < $endHour;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function rowsToCountMap(array $rows, string $idKey): array
    {
        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row[$idKey];
            if ($id > 0) {
                $map[$id] = (int) $row['total'];
            }
        }
        return $map;
    }

    /**
     * @param array<int, int> $left
     * @param array<int, int> $right
     * @return array<int, int>
     */
    private function mergeCounts(array $left, array $right): array
    {
        $merged = $left;
        foreach ($right as $key => $value) {
            $merged[$key] = ($merged[$key] ?? 0) + $value;
        }
        return $merged;
    }

    private function computeChurnScore(int $daysSinceLast, int $activityCount): float
    {
        $score = 0.0;

        $cap = 90.0;
        $ratio = min(1.0, $daysSinceLast / $cap);
        $score += $ratio * 60.0;

        if ($activityCount === 0) {
            $score += 25.0;
        } elseif ($activityCount <= 2) {
            $score += 10.0;
        }

        return min(100.0, $score);
    }

    private function sigmoid(float $x): float
    {
        return 1.0 / (1.0 + exp(-$x));
    }

    private function diffDays(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $from->diff($to)->format('%a');
    }

    private function maxDate(?\DateTimeInterface ...$dates): ?\DateTimeInterface
    {
        $max = null;
        foreach ($dates as $date) {
            if ($date === null) {
                continue;
            }
            if (!$max || $date > $max) {
                $max = $date;
            }
        }
        return $max;
    }

    private function formatFloat(float $value, int $precision): float
    {
        return (float) number_format($value, $precision, '.', '');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function assignChurnLabels(array &$rows): void
    {
        $count = count($rows);
        if ($count === 0) {
            return;
        }

        if ($count === 1) {
            $rows[0]['is_churn'] = 0;
            return;
        }

        $scores = [];
        foreach ($rows as $index => $row) {
            $scores[$index] = (float) ($row['_churn_score'] ?? 0.0);
        }

        arsort($scores);
        $topCount = max(1, (int) round($count * 0.3));
        if ($topCount >= $count) {
            $topCount = $count - 1;
        }

        $topIndexes = array_slice(array_keys($scores), 0, $topCount);
        $topMap = array_fill_keys($topIndexes, true);

        foreach ($rows as $index => &$row) {
            $row['is_churn'] = isset($topMap[$index]) ? 1 : 0;
        }
        unset($row);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $path, array $rows): int
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $headers = [
            'user_id',
            'user_name',
            'role',
            'region',
            'device_type',
            'account_age_days',
            'last_activity_days',
            'demands_30d',
            'appointments_30d',
            'cancellations_30d',
            'cancel_rate_30d',
            'abnormal_night_actions_30d',
            'shared_phone_count',
            'payment_failures_30d',
            'chargebacks_30d',
            'avg_payment_amount_30d',
            'is_fraud',
            'is_churn',
        ];

        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ecrire le fichier CSV: ' . $path);
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $key) {
                $line[] = $row[$key] ?? null;
            }
            fputcsv($handle, $line);
        }

        fclose($handle);
        return count($rows);
    }
}
