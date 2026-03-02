# Sahty

## Semantic Search Integration (Python + Symfony)

Starter disponible dans:

- `sahty_sym/ai/semantic_search_service`

Symfony appelle l'API Python sur `FASTAPI_SEMANTIC_ENDPOINT` pendant la recherche produits (`/recherche-produits`) avec fallback automatique vers le modèle local PHP si l'API est indisponible.
