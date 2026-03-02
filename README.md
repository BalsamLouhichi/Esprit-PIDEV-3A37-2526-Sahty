# Sahty

## Analyse Du Projet

### Vue D'Ensemble
Sahty est une plateforme sante construite principalement avec Symfony (`sahty_sym`) et enrichie par des services IA.
Le projet couvre plusieurs domaines metier: gestion des utilisateurs, rendez-vous, analyses medicales, laboratoire, parapharmacie, quiz/recommandations, paiements et administration.

### Objectifs Fonctionnels
- Centraliser le parcours patient (inscription, profil, rendez-vous, analyses).
- Permettre aux medecins/laboratoires de gerer les demandes d'analyses et les resultats.
- Fournir une couche de recommandation via quiz et services IA.
- Integrer des usages e-commerce/parapharmacie (produits, panier, commande, paiement).

### Architecture Technique
- Backend principal: Symfony 6.4 + Doctrine ORM + Twig.
- Donnees: MySQL via migrations Doctrine.
- IA/ML: services Python/FastAPI connectes via endpoint (recherche semantique, services d'analyse).
- Asynchrone: Symfony Messenger (`messenger_messages`) pour les traitements deferes.
- Qualite: PHPUnit pour les tests unitaires, PHPStan pour l'analyse statique.

### Structure Metier (Haut Niveau)
- `src/Entity`: modeles metier (Utilisateur, Patient, Medecin, DemandeAnalyse, ResultatAnalyse, Produit, etc.).
- `src/Controller`: endpoints web et backoffice.
- `src/Service`: logique applicative/metier (managers, IA, reporting, notifications).
- `src/DataFixtures`: jeu de donnees initiales pour dev/tests.
- `migrations`: evolution de schema de base de donnees.

### Points Forts
- Couverture fonctionnelle riche et multi-domaines.
- Separation claire Entity/Service/Controller.
- Presence de tests unitaires sur des services critiques.
- Integration progressive de l'IA avec fallback pour la robustesse.

### Risques Techniques Identifies
- Historique de migrations volumineux et parfois redondant (risque de conflits sur DB vierge).
- Heterogeneite de typage dans certains controllers (normalisation des donnees HTTP a renforcer).
- Dette technique sur certaines zones legacy necessitant un nettoyage progressif.

### Recommandations De Stabilisation
1. Imposer une convention stricte pour les nouvelles migrations (une seule source de verite schema).
2. Renforcer la validation/normalisation des entrees HTTP avant hydration des entites.
3. Ajouter des tests unitaires autour des services metier sensibles (analyse, paiement, rdv).
4. Traiter progressivement les erreurs PHPStan du baseline pour reduire la dette.


## Semantic Search Integration (Python + Symfony)

Starter disponible dans:

- `sahty_sym/ai/semantic_search_service`

Symfony appelle l'API Python sur `FASTAPI_SEMANTIC_ENDPOINT` pendant la recherche produits (`/recherche-produits`) avec fallback automatique vers le modèle local PHP si l'API est indisponible.
`

## Extraction Et Analyse De Bilan

Ce module permet de traiter un bilan medical PDF de bout en bout:
- Reception du fichier PDF depuis le workflow `DemandeAnalyse`.
- Extraction des donnees utiles (valeurs, unites, intervalles, metadonnees).
- Analyse par service IA pour detection d'anomalies et niveau de risque.
- Generation d'un resume interpretable pour affichage applicatif.
- Enregistrement du resultat dans `ResultatAnalyse` avec statut de traitement.

### Commandes Utiles
```bash
# Lancer les tests unitaires
cd sahty_sym
vendor/bin/phpunit

# Analyse statique
vendor/bin/phpstan analyse src/Controller

# Charger les fixtures (dev)
php bin/console doctrine:fixtures:load --no-interaction
``
