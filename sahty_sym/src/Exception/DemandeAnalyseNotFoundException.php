<?php

namespace App\Exception;

final class DemandeAnalyseNotFoundException extends \RuntimeException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('DemandeAnalyse #%d introuvable.', $id));
    }
}
