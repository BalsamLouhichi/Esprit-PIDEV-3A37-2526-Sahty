<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class InscriptionPreferencesStorageService
{
    private string $storagePath;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $projectDirValue = $parameterBag->get('kernel.project_dir');
        $projectDir = is_scalar($projectDirValue) ? (string) $projectDirValue : '';
        $this->storagePath = $projectDir . '/var/inscription_preferences.json';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveForInscription(int $inscriptionId, array $payload): void
    {
        /** @var array<string, array<string, mixed>> $all */
        $all = $this->readAll();
        $all[(string) $inscriptionId] = $payload;
        $this->writeAll($all);
    }

    public function removeForInscription(int $inscriptionId): void
    {
        /** @var array<string, array<string, mixed>> $all */
        $all = $this->readAll();
        unset($all[(string) $inscriptionId]);
        $this->writeAll($all);
    }

    /**
     * @return array<string, mixed>
     */
    public function getForInscription(int $inscriptionId): array
    {
        $all = $this->readAll();
        return $all[(string) $inscriptionId] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getForEvent(int $eventId): array
    {
        $all = $this->readAll();
        $result = [];

        foreach ($all as $inscriptionId => $item) {
            if (($item['event_id'] ?? null) === $eventId) {
                $result[(string) $inscriptionId] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readAll(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        $raw = (string) @file_get_contents($this->storagePath);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, array<string, mixed>> $payload
     */
    private function writeAll(array $payload): void
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @file_put_contents(
            $this->storagePath,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
