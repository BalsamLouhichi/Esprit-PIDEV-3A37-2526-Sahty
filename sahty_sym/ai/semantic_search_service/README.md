# Semantic Search Service (Python)

Microservice FastAPI pour recherche sémantique de produits parapharmacie à partir d'un CSV.

## 1) Installation

```powershell
cd Sahty\sahty_sym\ai\semantic_search_service
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## 2) Préparer le CSV

Colonnes obligatoires:

- `product_id`
- `nom`
- `categorie`
- `marque`
- `description`
- `est_actif`

Copier `data/products.sample.csv` vers `data/products.csv`, ou exporter depuis Symfony/MySQL.

## 3) Lancer l'API

```powershell
$env:SEMANTIC_PRODUCTS_CSV="C:\path\to\products.csv"
$env:SEMANTIC_MODEL_NAME="sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
uvicorn app:app --host 127.0.0.1 --port 8091
```

## 4) Endpoints

- `GET /health`
- `POST /reindex`
- `POST /search`

Exemple recherche:

```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8091/search" -Method Post -ContentType "application/json" -Body '{"query":"j ai la gorge irritee","limit":5}'
```
