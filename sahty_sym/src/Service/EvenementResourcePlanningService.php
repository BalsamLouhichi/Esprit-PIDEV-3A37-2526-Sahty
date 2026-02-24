<?php

namespace App\Service;

class EvenementResourcePlanningService
{
    public function __construct(
        private readonly array $stockSnapshot = []
    ) {
    }

    public function buildPlan(string $type, string $mode, ?int $placesMax): array
    {
        $participants = max(1, (int) ($placesMax ?? 30));
        $requirements = $this->estimateRequirements($type, $mode, $participants);

        $lines = [];
        $riskScore = 0.0;
        $actions = [];

        foreach ($requirements as $key => $required) {
            $available = (int) ($this->stockSnapshot[$key] ?? 0);
            $shortage = max(0, $required - $available);
            $shortageRatio = $required > 0 ? ($shortage / $required) : 0.0;
            $riskScore += $shortageRatio;

            if ($shortage > 0) {
                $actions[] = sprintf('Prevoir %d unite(s) supplementaire(s) pour "%s".', $shortage, $this->labelFor($key));
            }

            $lines[] = [
                'ressource' => $this->labelFor($key),
                'required' => $required,
                'available' => $available,
                'shortage' => $shortage,
                'status' => $shortage === 0 ? 'ok' : ($shortageRatio > 0.2 ? 'critique' : 'attention'),
            ];
        }

        $riskLevel = $this->riskLevelFromScore($riskScore, count($requirements));
        $riskMessage = match ($riskLevel) {
            'faible' => 'Stock globalement adequat pour cet evenement.',
            'moyen' => 'Quelques tensions detectees, prevoir des ajustements avant validation.',
            default => 'Risque eleve de rupture, action logistique requise avant validation.',
        };

        if ($mode === 'presentiel' || $mode === 'hybride') {
            $actions[] = 'Verifier la disponibilite du lieu et confirmer les equipements AV 48h avant.';
        }

        if (count($actions) === 0) {
            $actions[] = 'Aucune action critique: vous pouvez valider avec suivi standard.';
        }

        return [
            'meta' => [
                'participants_estimes' => $participants,
                'type' => $type,
                'mode' => $mode,
            ],
            'risk' => [
                'level' => $riskLevel,
                'message' => $riskMessage,
            ],
            'resources' => $lines,
            'actions' => array_values(array_unique($actions)),
        ];
    }

    private function estimateRequirements(string $type, string $mode, int $participants): array
    {
        $type = mb_strtolower(trim($type));
        $mode = mb_strtolower(trim($mode));

        $base = [
            'chaises' => (int) ceil($participants * 1.0),
            'micros' => $participants > 80 ? 3 : 2,
            'projecteurs' => 1,
            'kits_accueil' => (int) ceil($participants * 0.9),
            'linens' => (int) ceil($participants / 12),
        ];

        if ($type === 'depistage') {
            $base['kits_depistage'] = (int) ceil($participants * 0.55);
            $base['gants'] = (int) ceil($participants * 2.2);
            $base['masques'] = (int) ceil($participants * 1.2);
        } elseif ($type === 'atelier') {
            $base['kits_atelier'] = (int) ceil($participants * 0.65);
            $base['tables'] = (int) ceil($participants / 6);
        } elseif ($type === 'conference' || $type === 'formation') {
            $base['tables'] = (int) ceil($participants / 10);
            $base['speaker_monitors'] = 1;
        }

        if ($mode === 'en_ligne') {
            $base['chaises'] = 0;
            $base['tables'] = 0;
            $base['linens'] = 0;
            $base['projecteurs'] = 0;
        } elseif ($mode === 'hybride') {
            $base['chaises'] = (int) ceil($base['chaises'] * 0.6);
            $base['kits_accueil'] = (int) ceil($base['kits_accueil'] * 0.6);
        }

        return array_filter($base, static fn (int $value): bool => $value > 0);
    }

    private function riskLevelFromScore(float $totalShortageRatio, int $resourceCount): string
    {
        if ($resourceCount === 0) {
            return 'faible';
        }

        $normalized = $totalShortageRatio / $resourceCount;
        if ($normalized <= 0.05) {
            return 'faible';
        }
        if ($normalized <= 0.20) {
            return 'moyen';
        }

        return 'eleve';
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            'chaises' => 'Chaises',
            'tables' => 'Tables',
            'micros' => 'Microphones',
            'projecteurs' => 'Projecteurs',
            'linens' => 'Linge / nappage',
            'kits_accueil' => 'Kits d\'accueil',
            'kits_depistage' => 'Kits de depistage',
            'kits_atelier' => 'Kits atelier',
            'gants' => 'Gants',
            'masques' => 'Masques',
            'speaker_monitors' => 'Moniteur intervenant',
            default => ucfirst(str_replace('_', ' ', $key)),
        };
    }
}

