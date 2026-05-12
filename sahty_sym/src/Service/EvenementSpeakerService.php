<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Medecin;
use App\Repository\MedecinRepository;

class EvenementSpeakerService
{
    private const SPEAKERS_HEADER = 'Speakers retenus:';
    private MedecinRepository $medecinRepository;

    /**
     * @var array<int, array<string, string>>
     */
    private const EXTERNAL_SPEAKERS = [
        [
            'name' => 'Dr. Amal Trabelsi',
            'specialite' => 'Cardiologie preventive',
            'ville' => 'Tunis',
            'source' => 'Externe',
            'url' => 'https://www.linkedin.com',
            'details' => 'Intervenante sante publique et prevention cardiovasculaire.',
        ],
        [
            'name' => 'Dr. Youssef Ben Salem',
            'specialite' => 'Diabetologie',
            'ville' => 'Sfax',
            'source' => 'Externe',
            'url' => 'https://www.researchgate.net',
            'details' => 'Conference et education therapeutique du patient.',
        ],
        [
            'name' => 'Pr. Ines Gharbi',
            'specialite' => 'Nutrition clinique',
            'ville' => 'Sousse',
            'source' => 'Externe',
            'url' => '',
            'details' => 'Nutrition, ateliers et prevention lifestyle.',
        ],
        [
            'name' => 'Dr. Mohamed Khelifi',
            'specialite' => 'Sante mentale',
            'ville' => 'Monastir',
            'source' => 'Externe',
            'url' => '',
            'details' => 'Animation de groupes de parole et conferences bien-etre.',
        ],
    ];

    public function __construct(MedecinRepository $medecinRepository)
    {
        $this->medecinRepository = $medecinRepository;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function recommendSpeakers(?Evenement $evenement = null): array
    {
        $recommendations = [];

        foreach ($this->recommendInternalSpeakers($evenement) as $speaker) {
            $recommendations[$speaker['key']] = $speaker;
        }

        foreach ($this->recommendExternalSpeakers($evenement) as $speaker) {
            $recommendations[$speaker['key']] = $speaker;
        }

        return array_values($recommendations);
    }

    /**
     * @param array<int, string> $speakerKeys
     * @return array<int, array<string, string>>
     */
    public function resolveSelectedSpeakers(array $speakerKeys, ?Evenement $evenement = null): array
    {
        $catalog = [];
        foreach ($this->recommendSpeakers($evenement) as $speaker) {
            $catalog[$speaker['key']] = $speaker;
        }

        $selected = [];
        foreach ($speakerKeys as $speakerKey) {
            $key = trim((string) $speakerKey);
            if ($key === '' || !isset($catalog[$key])) {
                continue;
            }
            $selected[$key] = $catalog[$key];
            if (count($selected) >= 2) {
                break;
            }
        }

        return array_values($selected);
    }

    /**
     * @param array<int, array<string, string>> $selectedSpeakers
     */
    public function buildSpeakersBlock(array $selectedSpeakers): string
    {
        if ($selectedSpeakers === []) {
            return '';
        }

        $lines = [self::SPEAKERS_HEADER];
        foreach (array_slice($selectedSpeakers, 0, 2) as $speaker) {
            $lines[] = sprintf(
                '- %s | %s | %s | %s',
                $speaker['name'],
                $speaker['specialite'],
                $speaker['ville'],
                $speaker['source']
            );
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param array<int, array<string, string>> $selectedSpeakers
     */
    public function injectSpeakersIntoPlanning(?string $planningRecommande, array $selectedSpeakers): ?string
    {
        $cleanPlanning = $this->removeSpeakersBlock($planningRecommande);
        $block = $this->buildSpeakersBlock($selectedSpeakers);

        if ($block === '') {
            return $cleanPlanning !== '' ? $cleanPlanning : null;
        }

        if ($cleanPlanning === '') {
            return $block;
        }

        return trim($cleanPlanning . PHP_EOL . PHP_EOL . $block);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function parseSpeakersFromPlanning(?string $planningRecommande): array
    {
        $content = trim((string) $planningRecommande);
        if ($content === '') {
            return [];
        }

        $lines = preg_split('/\R/', $content) ?: [];
        $inBlock = false;
        $speakers = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === self::SPEAKERS_HEADER) {
                $inBlock = true;
                continue;
            }

            if (!$inBlock) {
                continue;
            }

            if (!str_starts_with($trimmed, '- ')) {
                if ($trimmed !== '') {
                    break;
                }
                continue;
            }

            $payload = substr($trimmed, 2);
            $parts = array_map('trim', explode('|', $payload));
            $speakers[] = [
                'name' => $parts[0] ?? '',
                'specialite' => $parts[1] ?? '',
                'ville' => $parts[2] ?? '',
                'source' => $parts[3] ?? '',
            ];
        }

        return array_values(array_filter(
            array_slice($speakers, 0, 2),
            static fn (array $speaker): bool => trim((string) ($speaker['name'] ?? '')) !== ''
        ));
    }

    /**
     * @return array<int, string>
     */
    public function extractSelectedKeysFromPlanning(?string $planningRecommande, ?Evenement $evenement = null): array
    {
        $selected = $this->parseSpeakersFromPlanning($planningRecommande);
        if ($selected === []) {
            return [];
        }

        $catalog = [];
        foreach ($this->recommendSpeakers($evenement) as $speaker) {
            $catalog[$speaker['key']] = $speaker;
        }

        $keys = [];
        foreach ($selected as $speaker) {
            foreach ($catalog as $key => $candidate) {
                if (
                    mb_strtolower($candidate['name']) === mb_strtolower($speaker['name'])
                    && mb_strtolower($candidate['specialite']) === mb_strtolower($speaker['specialite'])
                ) {
                    $keys[] = $key;
                    break;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function recommendInternalSpeakers(?Evenement $evenement = null): array
    {
        $type = mb_strtolower(trim((string) ($evenement?->getType() ?? '')));
        $keywords = $this->keywordsForEventType($type);

        /** @var Medecin[] $medecins */
        $medecins = $this->medecinRepository->findBy(['estActif' => true]);
        $ranked = [];

        foreach ($medecins as $medecin) {
            if (!$medecin instanceof Medecin) {
                continue;
            }

            $specialite = trim((string) $medecin->getSpecialite());
            $name = trim($medecin->getPrenom() . ' ' . $medecin->getNom());
            if ($name === '') {
                continue;
            }

            $haystack = mb_strtolower($specialite . ' ' . (string) $medecin->getNomEtablissement());
            $score = 0;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($haystack, $keyword)) {
                    $score += 10;
                }
            }

            $ranked[] = [
                'key' => 'internal:' . $medecin->getId(),
                'name' => $name,
                'specialite' => $specialite !== '' ? $specialite : 'Generaliste',
                'ville' => trim((string) $medecin->getVille()) !== '' ? trim((string) $medecin->getVille()) : 'Ville non precisee',
                'source' => 'Plateforme',
                'url' => '',
                'details' => trim((string) $medecin->getNomEtablissement()),
                'score' => $score,
            ];
        }

        usort($ranked, static function (array $a, array $b): int {
            if (($a['score'] ?? 0) === ($b['score'] ?? 0)) {
                return strcmp($a['name'], $b['name']);
            }

            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        return array_map(
            static function (array $speaker): array {
                unset($speaker['score']);
                return $speaker;
            },
            array_slice($ranked, 0, 6)
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function recommendExternalSpeakers(?Evenement $evenement = null): array
    {
        $type = mb_strtolower(trim((string) ($evenement?->getType() ?? '')));
        $keywords = $this->keywordsForEventType($type);
        $ranked = [];

        foreach (self::EXTERNAL_SPEAKERS as $index => $speaker) {
            $haystack = mb_strtolower(($speaker['specialite'] ?? '') . ' ' . ($speaker['details'] ?? ''));
            $score = 0;
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($haystack, $keyword)) {
                    $score += 10;
                }
            }

            $ranked[] = [
                'key' => 'external:' . $index,
                'name' => $speaker['name'],
                'specialite' => $speaker['specialite'],
                'ville' => $speaker['ville'],
                'source' => $speaker['source'],
                'url' => $speaker['url'],
                'details' => $speaker['details'],
                'score' => $score,
            ];
        }

        usort($ranked, static function (array $a, array $b): int {
            if (($a['score'] ?? 0) === ($b['score'] ?? 0)) {
                return strcmp($a['name'], $b['name']);
            }

            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        return array_map(
            static function (array $speaker): array {
                unset($speaker['score']);
                return $speaker;
            },
            array_slice($ranked, 0, 4)
        );
    }

    /**
     * @return array<int, string>
     */
    private function keywordsForEventType(string $type): array
    {
        return match ($type) {
            'depistage' => ['depistage', 'prevention', 'diagnostic', 'cardio', 'diabete'],
            'conference' => ['conference', 'cardio', 'nutrition', 'sante mentale', 'prevention'],
            'formation' => ['formation', 'pedagogie', 'simulation', 'nutrition', 'prevention'],
            'groupe_parole' => ['sante mentale', 'psych', 'accompagnement', 'bien etre'],
            'atelier' => ['atelier', 'nutrition', 'education', 'prevention'],
            default => ['sante', 'prevention'],
        };
    }

    private function removeSpeakersBlock(?string $planningRecommande): string
    {
        $content = trim((string) $planningRecommande);
        if ($content === '') {
            return '';
        }

        $clean = preg_replace(
            '/(?:^|\R)Speakers retenus:\R(?:- .*(?:\R|$))+/u',
            PHP_EOL,
            $content
        );

        $clean = preg_replace("/\R{3,}/", PHP_EOL . PHP_EOL, (string) $clean) ?? (string) $clean;

        return trim((string) $clean);
    }
}
