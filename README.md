# Sahty

## Semantic Search Integration (Python + Symfony)

Starter disponible dans:

- `sahty_sym/ai/semantic_search_service`

Symfony appelle l'API Python sur `FASTAPI_SEMANTIC_ENDPOINT` pendant la recherche produits (`/recherche-produits`) avec fallback automatique vers le modele local PHP si l'API est indisponible.

## Travail demande (PIDEV Sprint Web)

Selon les ateliers et le mail de consignes, le travail a realiser est:

1. Realisation des tests unitaires
2. Realisation des tests statiques avec PHPStan
3. Analyse de l'application avec DoctrineDoctor
4. Valeur ajoutee dans l'application (integration/amelioration)
5. Integration des travaux sur une seule machine via GitHub

## Etapes de realisation

### 1) Tests unitaires

Commandes:

```powershell
cd .\sahty_sym
php .\bin\phpunit tests\Service\QuizResultServiceTest.php
php .\bin\phpunit tests\Service\RecommandationServiceTest.php
```

### 2) Tests statiques PHPStan

Commandes:

```powershell
cd .\sahty_sym
composer require --dev phpstan/phpstan
php .\vendor\bin\phpstan analyse -c phpstan.neon
```

### 3) Analyse DoctrineDoctor

Commandes:

```powershell
cd .\sahty_sym
composer require --dev ahmed-bhs/doctrine-doctor
```

Si conflit de dependances:

```powershell
composer require ahmed-bhs/doctrine-doctor:^1.0 webmozart/assert:^1.11 --with-all-dependencies
composer require --dev ahmed-bhs/doctrine-doctor
```

Ensuite:

1. Ouvrir l'application en environnement `dev`
2. Ouvrir le Symfony Profiler
3. Ouvrir l'onglet `DoctrineDoctor`
4. Corriger les problemes signales
5. Relancer et verifier

```powershell
php .\bin\console cache:clear
```

### 4) Valeur ajoutee

1. Implementer une amelioration utile
2. Expliquer l'objectif et l'impact
3. Ajouter preuves/captures avant-apres

### 5) Integration GitHub (une seule machine)

1. Integrer toutes les contributions sur une machine
2. Verifier tests + analyse statique
3. Pousser la version finale

## Etat d'avancement (Quiz + Recommandation)

### Deja fait

- Tests unitaires Quiz: OK (`6 tests, 19 assertions`)
- Tests unitaires Recommandation: OK (`10 tests, 23 assertions`)
- Configuration PHPStan ciblee creee: `sahty_sym/phpstan.neon`
- Rapport de travail cree:
  - `sahty_sym/RAPPORT_QUIZ_RECOMMANDATION.md`
  - `sahty_sym/RAPPORT_QUIZ_RECOMMANDATION.docx`
- Script d'execution des tests cree:
  - `sahty_sym/run_tests.ps1`

### A finaliser

- Installation locale de PHPStan (bloquee sur ce poste par erreur SSL Composer)
- Execution de l'analyse PHPStan + captures (a lancer apres correction SSL)
- Execution DoctrineDoctor + captures avant/apres (a lancer apres correction SSL)
- Integration GitHub finale d'equipe: OK (push effectue sur `origin/iheb`, commit `d39bd45`)

## Script rapide (PowerShell)

```powershell
cd .\sahty_sym
.\run_tests.ps1
```
