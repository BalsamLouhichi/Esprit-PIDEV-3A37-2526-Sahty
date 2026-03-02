# Workshop Symfony 6.4 - Guide pratique (Sprint Web)

Ce guide suit la logique des ateliers envoyes:

1. Tests statiques (PHPStan)
2. Tests unitaires
3. DoctrineDoctor
4. Rapport de performance
5. Integration GitHub sur une seule machine

---

## 0) Positionnement projet

```powershell
cd c:\Users\LENOVO\Downloads\projina\sahty_sym
```

Si ton dossier contient des espaces, utilise toujours des guillemets:

```powershell
cd "C:\Users\LENOVO\Downloads\projet allah ysahel\projet allah ysahel\sahty_sym"
```

Si PowerShell affiche `>>`, cela veut dire qu'une commande est incomplete.
Fais `Ctrl + C` pour annuler, puis retape la commande.

---

## 1) Tests statiques avec PHPStan

### Fichiers concernes
- `src/Service/QuizResultService.php`
- `src/Service/RecommandationService.php`
- `tests/Service/QuizResultServiceTest.php`
- `tests/Service/RecommandationServiceTest.php`
- `phpstan.neon` (a la racine de `sahty_sym`)

### Commandes

```powershell
Test-Path .\phpstan.neon
composer require --dev phpstan/phpstanTest-Path .\vendor\bin\phpstan

php .\vendor\bin\phpstan analyse -c phpstan.neon src\Service\QuizResultService.php src\Service\RecommandationService.php tests\Service\QuizResultServiceTest.php tests\Service\RecommandationServiceTest.php
```

### Captures a joindre
- Installation PHPStan
- 1er run PHPStan (avant correction)
- Run PHPStan apres correction

### En cas d'erreur SSL Composer

```powershell
Invoke-WebRequest https://curl.se/ca/cacert.pem -OutFile C:\composer\cacert.pem
composer config --global cafile C:\composer\cacert.pem
```

Puis dans `C:\xampp\php\php.ini`:

```ini
curl.cainfo = "C:\composer\cacert.pem"
openssl.cafile = "C:\composer\cacert.pem"
```

Verification:

```powershell
php -i | findstr /I "curl.cainfo openssl.cafile"
composer diagnose
```

### En cas d'erreur DNS (`curl error 28`)

Ouvrir PowerShell en mode Administrateur:

```powershell
Get-NetAdapter | Where-Object Status -eq "Up" | Select Name
```

Puis remplacer `Wi-Fi` par le nom exact affiche:

```powershell
netsh interface ip set dns name="Wi-Fi" static 1.1.1.1
netsh interface ip add dns name="Wi-Fi" 8.8.8.8 index=2
ipconfig /flushdns
netsh winsock reset
netsh int ip reset
```

Redemarrer Windows puis relancer:

```powershell
composer diagnose
composer require --dev phpstan/phpstan
```

---

## 2) Tests unitaires

### Fichiers de test
- `tests/Service/QuizResultServiceTest.php`
- `tests/Service/RecommandationServiceTest.php`

### Commandes

```powershell
php .\bin\phpunit --version
php .\bin\phpunit tests\Service\QuizResultServiceTest.php
php .\bin\phpunit tests\Service\RecommandationServiceTest.php
php .\bin\phpunit tests\Service\QuizResultServiceTest.php tests\Service\RecommandationServiceTest.php
```

### Resultat attendu (module Quiz/Recommandation)
- QuizResultServiceTest: `OK (6 tests, 19 assertions)`
- RecommandationServiceTest: `OK (10 tests, 23 assertions)`

### Captures a joindre
- Sortie terminal QuizResultServiceTest
- Sortie terminal RecommandationServiceTest
- (Optionnel) sortie combinee

---

## 3) DoctrineDoctor

### Installation

```powershell
composer require --dev ahmed-bhs/doctrine-doctor
```

Si conflit:

```powershell
composer require ahmed-bhs/doctrine-doctor:^1.0 webmozart/assert:^1.11 --with-all-dependencies
composer require --dev ahmed-bhs/doctrine-doctor
```

### Verification bundle
Verifier `config/bundles.php`:

```php
AhmedBhs\DoctrineDoctor\DoctrineDoctorBundle::class => ['dev' => true]
```

Puis:

```powershell
php .\bin\console cache:clear
```

### Utilisation
1. Lancer app en dev
2. Ouvrir une page
3. Ouvrir Symfony Profiler
4. Onglet DoctrineDoctor
5. Corriger les points signales (integrity/security/config/perf)

### Captures a joindre
- Panel DoctrineDoctor avant correction
- Code corrige (avant/apres)
- Panel DoctrineDoctor apres correction

---

## 4) Rapport de performance

### Tableau a remplir
| Indicateur | Avant optimisation | Apres optimisation | Preuves |
|---|---|---|---|
| Temps moyen page d'accueil (ms) | ... | ... | Capture Profiler |
| Temps fonctionnalite principale (quiz/recommandation) | ... | ... | Capture Timeline |
| Utilisation memoire | ... | ... | Capture Memory |
| Problemes DoctrineDoctor | ... | ... | Capture avant/apres |

### Regle de mesure
- Faire 5 mesures avant
- Appliquer corrections
- Faire 5 mesures apres
- Comparer moyenne

---

## 5) Integration GitHub (une seule machine)

### Objectif atelier
- Commits reguliers
- Integration finale sur une machine
- Verification finale

### Commandes utiles

```powershell
git status
git add .
git commit -m "workshop: tests + static + doctor + report"
git push origin <branche>
```

---

## Etat de ta partie (Quiz/Recommandation)

### Deja fait
- Tests unitaires prets et valides
- `phpstan.neon` cree sous `sahty_sym`
- Rapport individuel cree
- Script `run_tests.ps1` cree

### A finaliser selon machine
- Installation PHPStan (si SSL reseau corrige)
- Installation DoctrineDoctor (si SSL reseau corrige)
- Captures avant/apres pour rapport
