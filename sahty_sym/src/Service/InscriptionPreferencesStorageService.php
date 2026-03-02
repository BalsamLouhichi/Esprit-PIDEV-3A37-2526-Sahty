<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class InscriptionPreferencesStorageService
{
    private string $storagePath;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $projectDirValue = $parameterBag->get('kernel.project_dir');
        $projectDir = is_string($projectDirValue) ? $projectDirValue : '';
        $this->storagePath = $projectDir . '/var/inscription_preferences.json';
    }

    public function saveForInscription(int $inscriptionId, array $payload): void
    {
        $all = $this->readAll();
        $all[(string) $inscriptionId] = $payload;
        $this->writeAll($all);
    }

    public function removeForInscription(int $inscriptionId): void
    {
        $all = $this->readAll();
        unset($all[(string) $inscriptionId]);
        $this->writeAll($all);
    }

    public function getForInscription(int $inscriptionId): array
    {
        $all = $this->readAll();
        return $all[(string) $inscriptionId] ?? [];
    }

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
        return is_array($decoded) ? $decoded : [];
    }

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
