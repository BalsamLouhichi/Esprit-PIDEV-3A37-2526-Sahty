$ErrorActionPreference = "Stop"

Write-Host "==> 1) Positionnement projet"
Set-Location -Path $PSScriptRoot

Write-Host "==> 2) Verification PHP"
php -v

Write-Host "==> 3) Verification dependances Composer"
if (-not (Test-Path ".\vendor")) {
    Write-Host "vendor absent -> installation des dependances"
    composer install
}

Write-Host "==> 4) Tests unitaires Quiz"
php .\bin\phpunit tests\Service\QuizResultServiceTest.php

Write-Host "==> 5) Tests unitaires Recommandation"
php .\bin\phpunit tests\Service\RecommandationServiceTest.php

Write-Host "==> 6) Tests combines"
php .\bin\phpunit tests\Service\QuizResultServiceTest.php tests\Service\RecommandationServiceTest.php

Write-Host "==> 7) Installation PHPStan si absent"
if (-not (Test-Path ".\vendor\bin\phpstan")) {
    composer require --dev phpstan/phpstan
}

Write-Host "==> 8) Analyse statique ciblee (Quiz + Recommandation)"
php .\vendor\bin\phpstan analyse -c phpstan.neon

Write-Host "==> Termine avec succes."
