$ErrorActionPreference = "Stop"

Set-Location -Path $PSScriptRoot

Write-Host "=== Workshop Step Runner (Symfony 6.4) ==="
Write-Host "Projet: $PSScriptRoot"

Write-Host "`n[0] Verification fichiers de base"
$required = @(
    ".\phpstan.neon",
    ".\tests\Service\QuizResultServiceTest.php",
    ".\tests\Service\RecommandationServiceTest.php"
)

foreach ($f in $required) {
    if (-not (Test-Path $f)) {
        Write-Host "Manquant: $f" -ForegroundColor Red
        exit 1
    }
}
Write-Host "Fichiers OK."

Write-Host "`n[1] Tests unitaires (Quiz/Recommandation)"
php .\bin\phpunit tests\Service\QuizResultServiceTest.php
php .\bin\phpunit tests\Service\RecommandationServiceTest.php

Write-Host "`n[2] Verification PHPStan"
if (-not (Test-Path ".\vendor\bin\phpstan")) {
    Write-Host "PHPStan non installe. Tentative d'installation..." -ForegroundColor Yellow
    composer require --dev phpstan/phpstan
}

if (Test-Path ".\vendor\bin\phpstan") {
    php .\vendor\bin\phpstan analyse -c phpstan.neon
} else {
    Write-Host "PHPStan reste indisponible (probable probleme SSL/Reseau)." -ForegroundColor Yellow
}

Write-Host "`n[3] Verification DoctrineDoctor"
php .\bin\console about | Out-Host

Write-Host "`nSi DoctrineDoctor n'est pas installe:"
Write-Host "composer require --dev ahmed-bhs/doctrine-doctor" -ForegroundColor Cyan
Write-Host "php .\bin\console cache:clear" -ForegroundColor Cyan

Write-Host "`nTermine."
