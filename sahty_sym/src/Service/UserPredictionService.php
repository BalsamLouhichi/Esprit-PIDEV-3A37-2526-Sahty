<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class UserPredictionService
{
    private EntityManagerInterface $em;
    /** @var array<string, int|float> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(EntityManagerInterface $em, array $config = [])
    {
        $this->em = $em;
        $this->config = array_merge([
            'since_days' => 30,
            'churn_no_activity_boost' => 60,
            'churn_days_weight' => 40,
            'churn_days_cap' => 90,
            'demand_score_per_request' => 20,
            'cancel_min_count' => 3,
        ], $config);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildPredictions(): array
    {
        $sinceDays = (int) $this->config['since_days'];
        $since = new \DateTimeImmutable('-' . $sinceDays . ' days');
        $now = new \DateTimeImmutable();

        $patients = $this->em->createQueryBuilder()
            ->select('u.id as id, u.creeLe as createdAt, u.role as role')
            ->from(Utilisateur::class, 'u')
            ->where('u.role = :role')
            ->setParameter('role', Utilisateur::ROLE_SIMPLE_PATIENT)
            ->getQuery()
            ->getResult();

        if (!$patients) {
            return [];
        }

        $patientIds = array_map(static fn (array $row): int => (int) $row['id'], $patients);

        $demandCounts = $this->countDemandsByPatientSince($since);
        $appointmentCounts = $this->countAppointmentsByPatientSince($since);
        $cancelStats = $this->getCancellationStatsSince($since);
        $lastDemandDates = $this->getLastDemandDates();
        $lastAppointmentDates = $this->getLastAppointmentDates();

        $results = [];
        foreach ($patients as $patient) {
            $userId = (int) $patient['id'];
            $createdAt = $patient['createdAt'] instanceof \DateTimeInterface
                ? $patient['createdAt']
                : $now;

            $lastDemand = $lastDemandDates[$userId] ?? null;
            $lastAppointment = $lastAppointmentDates[$userId] ?? null;
            $lastActivity = $this->maxDate($createdAt, $lastDemand, $lastAppointment) ?? $createdAt;

            $daysSinceLast = $this->diffDays($lastActivity, $now);
            $demands30d = $demandCounts[$userId] ?? 0;
            $appointments30d = $appointmentCounts[$userId] ?? 0;

            $cancel = $cancelStats[$userId] ?? ['total' => 0, 'cancellations' => 0, 'rate' => 0.0];

            $churnScore = $this->computeChurnScore($daysSinceLast, $demands30d + $appointments30d);
            $cancelScore = $this->computeCancelScore((int) $cancel['total'], (float) $cancel['rate']);
            $demandScore = $this->computeDemandScore($demands30d);

            $results[] = [
                'user_id' => $userId,
                'role' => (string) $patient['role'],
                'last_activity' => $lastActivity->format('Y-m-d H:i'),
                'activity_30d' => $demands30d + $appointments30d,
                'demands_30d' => $demands30d,
                'appointments_30d' => $appointments30d,
                'cancel_rate_30d' => round(((float) $cancel['rate']) * 100, 2),
                'churn_risk_score' => $churnScore,
                'churn_risk_label' => $this->riskLabel($churnScore),
                'cancel_risk_score' => $cancelScore,
                'cancel_risk_label' => $this->riskLabel($cancelScore),
                'demand_score' => $demandScore,
                'demand_label' => $this->riskLabel($demandScore),
            ];
        }

        usort($results, static function (array $a, array $b): int {
            return $b['churn_risk_score'] <=> $a['churn_risk_score'];
        });

        return $results;
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
     * @return array<int, array<string, float|int>>
     */
    private function getCancellationStatsSince(\DateTimeInterface $since): array
    {
        $totals = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, COUNT(r.id) as total')
            ->from(RendezVous::class, 'r')
            ->where('r.creeLe >= :since')
            ->andWhere('r.patient IS NOT NULL')
            ->groupBy('r.patient')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $cancelStatuses = ['Annule', 'annule', 'Annulé', 'annulé'];
        $cancellations = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, COUNT(r.id) as total')
            ->from(RendezVous::class, 'r')
            ->where('r.creeLe >= :since')
            ->andWhere('r.statut IN (:cancel)')
            ->andWhere('r.patient IS NOT NULL')
            ->groupBy('r.patient')
            ->setParameter('since', $since)
            ->setParameter('cancel', $cancelStatuses)
            ->getQuery()
            ->getResult();

        $cancelMap = [];
        foreach ($cancellations as $row) {
            $cancelMap[(int) $row['patientId']] = (int) $row['total'];
        }

        $stats = [];
        foreach ($totals as $row) {
            $patientId = (int) $row['patientId'];
            $total = (int) $row['total'];
            $cancelCount = $cancelMap[$patientId] ?? 0;
            $rate = $total > 0 ? $cancelCount / $total : 0.0;

            $stats[$patientId] = [
                'total' => $total,
                'cancellations' => $cancelCount,
                'rate' => $rate,
            ];
        }

        return $stats;
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

    private function computeChurnScore(int $daysSinceLast, int $activityCount): float
    {
        $score = 0.0;
        if ($activityCount === 0) {
            $score += (float) $this->config['churn_no_activity_boost'];
        }

        $cap = max(1, (int) $this->config['churn_days_cap']);
        $weight = (float) $this->config['churn_days_weight'];
        $ratio = min(1.0, $daysSinceLast / $cap);
        $score += $ratio * $weight;

        return min(100.0, $score);
    }

    private function computeCancelScore(int $totalAppointments, float $cancelRate): float
    {
        if ($totalAppointments <= 0) {
            return 0.0;
        }

        $minCount = (int) $this->config['cancel_min_count'];
        if ($totalAppointments < $minCount) {
            return round($cancelRate * 60, 2);
        }

        return round($cancelRate * 100, 2);
    }

    private function computeDemandScore(int $demandCount): float
    {
        $perRequest = (float) $this->config['demand_score_per_request'];
        return min(100.0, $demandCount * $perRequest);
    }

    private function riskLabel(float $score): string
    {
        if ($score >= 70) {
            return 'eleve';
        }
        if ($score >= 30) {
            return 'moyen';
        }
        return 'faible';
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

    private function diffDays(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $from->diff($to)->format('%a');
    }
}
