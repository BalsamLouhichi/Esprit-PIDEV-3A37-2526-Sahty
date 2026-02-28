# Rapport - Tests unitaires et analyse statique

## Etudiant
- Module traite: Gestion Quiz et Gestion Recommandation
- Projet: Symfony 6.4
- Date: 28/02/2026

## 1) Objectif de la tache
Realiser uniquement la partie demandee:
- tests unitaires sur la logique metier Quiz et Recommandation;
- test statique avec PHPStan sur les classes de service et leurs tests;
- fournir un compte rendu clair des actions realisees.

## 2) Perimetre fonctionnel couvert
### Gestion Quiz (`QuizResultService`)
- calcul du score total;
- gestion des questions inverses (reverse scoring);
- calcul des scores par categorie;
- detection des categories problematiques;
- filtrage des recommandations par score;
- interpretation textuelle du score;
- gestion des reponses vides.

### Gestion Recommandation (`RecommandationService`)
- filtrage des recommandations par score;
- filtrage par categories problematiques;
- tri par severite (high > medium > low);
- groupement et comptage par severite;
- extraction des recommandations urgentes;
- resolution des URLs video (normalisation YouTube + fallback categorie + fallback defaut).

## 3) Travaux realises
### 3.1 Tests unitaires
- Fichier corrige: `tests/Service/QuizResultServiceTest.php`
- Fichier deja valide: `tests/Service/RecommandationServiceTest.php`

Correction principale appliquee:
- En test unitaire pur, les entites `Question` n'ont pas d'ID Doctrine auto-genere.
- Le service Quiz utilise `getId()` pour lire les reponses.
- Solution: affectation d'IDs de test via reflection dans `QuizResultServiceTest`.

Resultats PHPUnit:
- `QuizResultServiceTest`: **OK (6 tests)**
- `RecommandationServiceTest`: **OK (10 tests)**
- Total module: **16 tests unitaires valides**

### 3.2 Analyse statique (PHPStan)
Configuration ajoutee:
- `phpstan.neon` (niveau 8)
- Perimetre cible:
  - `src/Service/QuizResultService.php`
  - `src/Service/RecommandationService.php`
  - `tests/Service/QuizResultServiceTest.php`
  - `tests/Service/RecommandationServiceTest.php`

Commande d'analyse:
```bash
vendor/bin/phpstan analyse -c phpstan.neon
```

Statut d'execution dans cet environnement:
- Installation `phpstan/phpstan` non finalisee (probleme SSL/reseau Composer local).
- La configuration est prete; lancer la commande des que Composer est operationnel.

## 4) Commandes executees / a executer
### PHPUnit (module seulement)
```bash
php bin/phpunit tests/Service/QuizResultServiceTest.php
php bin/phpunit tests/Service/RecommandationServiceTest.php
```

### PHPStan (module seulement)
```bash
composer require --dev phpstan/phpstan
vendor/bin/phpstan analyse -c phpstan.neon
```

## 5) Difficultes rencontrees et resolution
- **Probleme**: echec de plusieurs tests Quiz.
- **Cause**: dependance implicite aux IDs Doctrine non presents en unit test.
- **Correction**: generation d'IDs de test explicites dans les fixtures unitaires.

- **Probleme**: impossible d'installer PHPStan localement.
- **Cause**: erreur SSL/certificat vers Packagist.
- **Action**: configuration PHPStan creee et prete pour execution differree.

## 6) Conclusion
La partie demandee (Quiz + Recommandation) est preparee et validee cote tests unitaires.
L'analyse statique est configuree et ciblee sur le meme perimetre, avec execution a lancer apres resolution du probleme Composer/SSL de la machine.
