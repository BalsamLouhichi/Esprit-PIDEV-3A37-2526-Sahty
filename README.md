# Sahty

## Overview
Sahty est une plateforme de sante qui centralise le parcours patient et les operations medicales (rendez-vous, analyses, resultats, suivi), avec une integration de services IA pour enrichir l'analyse et la recommandation.
Ce projet a ete realise dans le cadre du programme PIDEV (3A) a **Esprit School of Engineering** pour l'annee academique 2025-2026.

## Features
- Gestion des utilisateurs (patients, medecins, laboratoires, administration)
- Gestion des demandes d'analyses et des resultats
- Prise de rendez-vous et suivi du parcours patient
- Modules e-commerce/parapharmacie (produits, panier, commande, paiement)
- Quiz et recommandations
- Integrations IA (analyse de bilan, recherche semantique)

## Tech Stack

### Frontend
- Twig (Symfony)
- HTML/CSS/JavaScript

### Backend
- Symfony 6.4 (PHP)
- Doctrine ORM
- MySQL
- Symfony Messenger
- Python/FastAPI (services IA)
- PHPUnit, PHPStan

## Architecture
- `sahty_sym/src/Entity` : modeles metier
- `sahty_sym/src/Controller` : endpoints web/backoffice
- `sahty_sym/src/Service` : logique applicative et metier
- `sahty_sym/migrations` : evolution du schema de base de donnees
- `sahty_sym/ai/semantic_search_service` : service de recherche semantique

## Contributors
- Equipe projet Sahty

## Academic Context
Developed at **Esprit School of Engineering - Tunisia**
PIDEV - 3A | 2025-2026

## Getting Started
```bash
# 1) Se placer dans le projet Symfony
cd sahty_sym

# 2) Installer les dependances PHP
composer install

# 3) Configurer la base de donnees dans .env
# DATABASE_URL="mysql://user:password@127.0.0.1:3306/sahty"

# 4) Appliquer les migrations
php bin/console doctrine:migrations:migrate

# 5) Charger les fixtures (optionnel)
php bin/console doctrine:fixtures:load --no-interaction

# 6) Lancer le serveur Symfony (terminal 1)
symfony server:start

# 7) Lancer le service IA FastAPI (terminal 2)
cd ai/semantic_search_service
pip install -r requirements.txt
uvicorn app:app --host 127.0.0.1 --port 8001 --reload

# Tests
cd ../../
vendor/bin/phpunit
```

## Acknowledgments
Merci a notre encadrante, et aux membres de l'equipe.
