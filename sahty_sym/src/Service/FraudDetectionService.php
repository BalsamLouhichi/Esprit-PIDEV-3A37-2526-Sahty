<?php

namespace App\Service;

use App\Entity\DemandeAnalyse;
use App\Entity\RendezVous;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class FraudDetectionService
{
    private EntityManagerInterface $em;
    private array $thresholds;
    private array $weights;

    /**
     * @param array<string, mixed> $thresholds
     * @param array<string, mixed> $weights
     */
    public function __construct(EntityManagerInterface $em, array $thresholds = [], array $weights = [])
    {
        $this->em = $em;
        $this->thresholds = array_merge([
            'max_requests_per_day' => 10,
            'max_appointments_per_day' => 5,
            'max_accounts_per_phone' => 2,
            'max_cancellations_30d' => 5,
            'max_cancel_rate_30d' => 0.5,
            'min_appointments_for_cancel_rate' => 5,
            'abnormal_hours_start' => 0,
            'abnormal_hours_end' => 6,
            'abnormal_hours_min_actions_30d' => 3,
        ], $thresholds);
        $this->weights = array_merge([
            'shared_phone' => 15,
            'high_daily_demands' => 20,
            'high_daily_appointments' => 15,
            'high_cancel_rate' => 25,
            'abnormal_hours' => 10,
        ], $weights);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generateSignals(): array
    {
        $signals = [];

        $signals = array_merge($signals, $this->detectSharedPhones());
        $signals = array_merge($signals, $this->detectHighDailyDemands());
        $signals = array_merge($signals, $this->detectHighDailyAppointments());
        $signals = array_merge($signals, $this->detectHighCancellationRates());
        $signals = array_merge($signals, $this->detectAbnormalHoursActivity());

        return $signals;
    }

    /**
     * @return array<string, mixed>
     */
    public function generateReport(): array
    {
        return [
            'signals' => $this->generateSignals(),
            'scores' => $this->buildRiskScores(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectSharedPhones(): array
    {
        $groups = $this->getSharedPhoneGroups();
        if (!$groups) {
            return [];
        }

        $items = [];
        foreach ($groups as $group) {
            $items[] = [
                'telephone' => $this->maskPhone($group['telephone']),
                'count' => (int) $group['count'],
            ];
        }

        return [[
            'type' => 'shared_phone',
            'severity' => 'medium',
            'title' => 'Telephones partages',
            'summary' => 'Plusieurs comptes partagent le meme numero.',
            'items' => $items,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectHighDailyDemands(): array
    {
        $maxPerDay = (int) $this->thresholds['max_requests_per_day'];
        $since = new \DateTimeImmutable('-1 day');
        $counts = $this->countDemandsByPatientSince($since);

        $items = [];
        foreach ($counts as $patientId => $count) {
            if ($count >= $maxPerDay) {
                $items[] = [
                    'patient_id' => $patientId,
                    'count' => $count,
                ];
            }
        }

        if (!$items) {
            return [];
        }

        return [[
            'type' => 'high_daily_demands',
            'severity' => 'high',
            'title' => 'Demandes d\'analyse elevees (24h)',
            'summary' => 'Nombre de demandes anormalement eleve pour un patient.',
            'items' => $items,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectHighDailyAppointments(): array
    {
        $maxPerDay = (int) $this->thresholds['max_appointments_per_day'];
        $since = new \DateTimeImmutable('-1 day');
        $counts = $this->countAppointmentsByPatientSince($since);

        $items = [];
        foreach ($counts as $patientId => $count) {
            if ($count >= $maxPerDay) {
                $items[] = [
                    'patient_id' => $patientId,
                    'count' => $count,
                ];
            }
        }

        if (!$items) {
            return [];
        }

        return [[
            'type' => 'high_daily_appointments',
            'severity' => 'medium',
            'title' => 'Rendez-vous eleves (24h)',
            'summary' => 'Nombre de rendez-vous anormalement eleve pour un patient.',
            'items' => $items,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectHighCancellationRates(): array
    {
        $maxCancelRate = (float) $this->thresholds['max_cancel_rate_30d'];
        $minTotal = (int) $this->thresholds['min_appointments_for_cancel_rate'];
        $maxCancelCount = (int) $this->thresholds['max_cancellations_30d'];

        $since = new \DateTimeImmutable('-30 days');
        $stats = $this->getCancellationStatsSince($since);

        $items = [];
        foreach ($stats as $patientId => $stat) {
            if ($stat['total'] < $minTotal) {
                continue;
            }

            if ($stat['rate'] >= $maxCancelRate || $stat['cancellations'] >= $maxCancelCount) {
                $items[] = [
                    'patient_id' => $patientId,
                    'total' => $stat['total'],
                    'cancellations' => $stat['cancellations'],
                    'rate' => round($stat['rate'] * 100, 2),
                ];
            }
        }

        if (!$items) {
            return [];
        }

        return [[
            'type' => 'high_cancellation_rate',
            'severity' => 'medium',
            'title' => 'Taux d\'annulation eleve (30j)',
            'summary' => 'Taux d\'annulation anormal pour certains patients.',
            'items' => $items,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectAbnormalHoursActivity(): array
    {
        $minActions = (int) $this->thresholds['abnormal_hours_min_actions_30d'];
        $since = new \DateTimeImmutable('-30 days');

        $counts = $this->getAbnormalActivityCounts($since);

        $items = [];
        foreach ($counts as $patientId => $count) {
            if ($count >= $minActions) {
                $items[] = [
                    'patient_id' => $patientId,
                    'count' => $count,
                ];
            }
        }

        if (!$items) {
            return [];
        }

        return [[
            'type' => 'abnormal_hours',
            'severity' => 'low',
            'title' => 'Activite nocturne (30j)',
            'summary' => 'Activites anormales pendant les heures de nuit.',
            'items' => $items,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRiskScores(): array
    {
        $since24 = new \DateTimeImmutable('-1 day');
        $since30 = new \DateTimeImmutable('-30 days');

        $dailyDemands = $this->countDemandsByPatientSince($since24);
        $dailyAppointments = $this->countAppointmentsByPatientSince($since24);
        $cancelStats = $this->getCancellationStatsSince($since30);
        $abnormalCounts = $this->getAbnormalActivityCounts($since30);
        $sharedGroups = $this->getSharedPhoneGroups();

        $risks = [];

        foreach ($sharedGroups as $group) {
            foreach ($group['user_ids'] as $userId) {
                $this->addRisk($risks, $userId, 'shared_phone', (float) $this->weights['shared_phone'], [
                    'shared_phone_count' => (int) $group['count'],
                ]);
            }
        }

        $maxDemands = (int) $this->thresholds['max_requests_per_day'];
        foreach ($dailyDemands as $patientId => $count) {
            if ($count >= $maxDemands) {
                $this->addRisk($risks, $patientId, 'high_daily_demands', (float) $this->weights['high_daily_demands'], [
                    'daily_demands' => $count,
                ]);
            }
        }

        $maxAppointments = (int) $this->thresholds['max_appointments_per_day'];
        foreach ($dailyAppointments as $patientId => $count) {
            if ($count >= $maxAppointments) {
                $this->addRisk($risks, $patientId, 'high_daily_appointments', (float) $this->weights['high_daily_appointments'], [
                    'daily_appointments' => $count,
                ]);
            }
        }

        $maxCancelRate = (float) $this->thresholds['max_cancel_rate_30d'];
        $minTotal = (int) $this->thresholds['min_appointments_for_cancel_rate'];
        $maxCancelCount = (int) $this->thresholds['max_cancellations_30d'];
        foreach ($cancelStats as $patientId => $stat) {
            if ($stat['total'] < $minTotal) {
                continue;
            }
            if ($stat['rate'] >= $maxCancelRate || $stat['cancellations'] >= $maxCancelCount) {
                $this->addRisk($risks, $patientId, 'high_cancel_rate', (float) $this->weights['high_cancel_rate'], [
                    'cancellations' => $stat['cancellations'],
                    'appointments_30d' => $stat['total'],
                    'cancel_rate' => round($stat['rate'] * 100, 2),
                ]);
            }
        }

        $minActions = (int) $this->thresholds['abnormal_hours_min_actions_30d'];
        foreach ($abnormalCounts as $patientId => $count) {
            if ($count >= $minActions) {
                $this->addRisk($risks, $patientId, 'abnormal_hours', (float) $this->weights['abnormal_hours'], [
                    'abnormal_hours_count' => $count,
                ]);
            }
        }

        if (!$risks) {
            return [];
        }

        $userIds = array_keys($risks);
        $meta = $this->fetchUserMeta($userIds);
        foreach ($risks as $userId => &$risk) {
            $risk['score'] = round($risk['score'], 2);
            $risk['summary'] = $this->buildRiskSummary($risk['metrics']);
            $risk['flags_label'] = $this->mapFlagLabels($risk['flags']);
            $risk['role'] = $meta[$userId]['role'] ?? 'unknown';
            $risk['phone_masked'] = isset($meta[$userId]['telephone'])
                ? $this->maskPhone($meta[$userId]['telephone'])
                : null;
        }
        unset($risk);

        $list = array_values($risks);
        usort($list, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return $list;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSharedPhoneGroups(): array
    {
        $minCount = (int) $this->thresholds['max_accounts_per_phone'];

        $rows = $this->em->createQueryBuilder()
            ->select('u.telephone as telephone, COUNT(u.id) as total')
            ->from(Utilisateur::class, 'u')
            ->where('u.telephone IS NOT NULL')
            ->groupBy('u.telephone')
            ->having('COUNT(u.id) >= :minCount')
            ->setParameter('minCount', $minCount)
            ->getQuery()
            ->getResult();

        if (!$rows) {
            return [];
        }

        $phones = [];
        foreach ($rows as $row) {
            $phones[] = (string) $row['telephone'];
        }

        $users = $this->em->createQueryBuilder()
            ->select('u.id as id, u.telephone as telephone')
            ->from(Utilisateur::class, 'u')
            ->where('u.telephone IN (:phones)')
            ->setParameter('phones', $phones)
            ->getQuery()
            ->getResult();

        $usersByPhone = [];
        foreach ($users as $userRow) {
            $phone = (string) $userRow['telephone'];
            $usersByPhone[$phone][] = (int) $userRow['id'];
        }

        $groups = [];
        foreach ($rows as $row) {
            $phone = (string) $row['telephone'];
            $groups[] = [
                'telephone' => $phone,
                'count' => (int) $row['total'],
                'user_ids' => $usersByPhone[$phone] ?? [],
            ];
        }

        return $groups;
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
            ->where('r.dateRdv >= :since')
            ->andWhere('r.patient IS NOT NULL')
            ->groupBy('r.patient')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $cancelStatuses = ['Annule', 'annule', 'Annulé', 'annulé'];
        $cancellations = $this->em->createQueryBuilder()
            ->select('IDENTITY(r.patient) as patientId, COUNT(r.id) as total')
            ->from(RendezVous::class, 'r')
            ->where('r.dateRdv >= :since')
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
     * @return array<int, int>
     */
    private function getAbnormalActivityCounts(\DateTimeInterface $since): array
    {
        $startHour = (int) $this->thresholds['abnormal_hours_start'];
        $endHour = (int) $this->thresholds['abnormal_hours_end'];

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

    /**
     * @param array<int, int> $userIds
     * @return array<int, array{role: string, telephone: string|null}>
     */
    private function fetchUserMeta(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('u.id as id, u.role as role, u.telephone as telephone')
            ->from(Utilisateur::class, 'u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getResult();

        $meta = [];
        foreach ($rows as $row) {
            $meta[(int) $row['id']] = [
                'role' => (string) $row['role'],
                'telephone' => $row['telephone'] !== null ? (string) $row['telephone'] : null,
            ];
        }

        return $meta;
    }

    /**
     * @param array<int, array<string, mixed>> $risks
     * @param array<string, mixed> $metrics
     */
    private function addRisk(array &$risks, int $userId, string $flag, float $score, array $metrics = []): void
    {
        if (!isset($risks[$userId])) {
            $risks[$userId] = [
                'user_id' => $userId,
                'score' => 0.0,
                'flags' => [],
                'metrics' => [],
            ];
        }

        if (!in_array($flag, $risks[$userId]['flags'], true)) {
            $risks[$userId]['flags'][] = $flag;
        }

        $risks[$userId]['score'] += $score;
        foreach ($metrics as $key => $value) {
            $risks[$userId]['metrics'][$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function buildRiskSummary(array $metrics): string
    {
        $parts = [];
        if (isset($metrics['daily_demands'])) {
            $parts[] = 'Demandes 24h: ' . $metrics['daily_demands'];
        }
        if (isset($metrics['daily_appointments'])) {
            $parts[] = 'RDV 24h: ' . $metrics['daily_appointments'];
        }
        if (isset($metrics['cancellations'])) {
            $parts[] = 'Annulations 30j: ' . $metrics['cancellations'] . '/' . ($metrics['appointments_30d'] ?? 0)
                . ' (' . ($metrics['cancel_rate'] ?? 0) . '%)';
        }
        if (isset($metrics['abnormal_hours_count'])) {
            $parts[] = 'Nuit 30j: ' . $metrics['abnormal_hours_count'];
        }
        if (isset($metrics['shared_phone_count'])) {
            $parts[] = 'Tel partage: ' . $metrics['shared_phone_count'];
        }

        return $parts ? implode(' | ', $parts) : 'Aucun detail';
    }

    /**
     * @param array<int, string> $flags
     * @return array<int, string>
     */
    private function mapFlagLabels(array $flags): array
    {
        $labels = [
            'shared_phone' => 'Telephone partage',
            'high_daily_demands' => 'Demandes 24h elevees',
            'high_daily_appointments' => 'RDV 24h eleves',
            'high_cancel_rate' => 'Annulations elevees',
            'abnormal_hours' => 'Heures nocturnes',
        ];

        $result = [];
        foreach ($flags as $flag) {
            $result[] = $labels[$flag] ?? $flag;
        }

        return $result;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
            return $phone;
        }

        $len = strlen($digits);
        $visible = substr($digits, -2);
        return str_repeat('*', max(0, $len - 2)) . $visible;
    }
}
