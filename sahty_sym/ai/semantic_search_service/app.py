from __future__ import annotations

from pathlib import Path
from threading import Lock
from typing import Any

import numpy as np
import pandas as pd
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from sentence_transformers import SentenceTransformer


BASE_DIR = Path(__file__).resolve().parent
DEFAULT_CSV = BASE_DIR / "data" / "products.csv"
DEFAULT_MODEL = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"


class SearchRequest(BaseModel):
    query: str = Field(min_length=1)
    limit: int = Field(default=30, ge=1, le=100)
    category: str | None = None
    active_only: bool = True
    semantic_keywords: list[str] = Field(default_factory=list)


class ReindexRequest(BaseModel):
    csv_path: str | None = None


class SemanticSearchEngine:
    REQUIRED_COLUMNS = {
        "product_id",
        "nom",
        "categorie",
        "marque",
        "description",
        "est_actif",
    }

    def __init__(self, model_name: str, csv_path: Path) -> None:
        self._lock = Lock()
        self._model_name = model_name
        self._model = SentenceTransformer(model_name)
        self._csv_path = csv_path
        self._df = pd.DataFrame()
        self._embeddings: np.ndarray | None = None
        self.reindex(csv_path)

    def reindex(self, csv_path: Path | None = None) -> dict[str, Any]:
        with self._lock:
            target = (csv_path or self._csv_path).expanduser().resolve()
            if not target.exists():
                raise FileNotFoundError(f"CSV file not found: {target}")

            df = pd.read_csv(target)
            missing = sorted(self.REQUIRED_COLUMNS - set(df.columns))
            if missing:
                raise ValueError(f"Missing columns in CSV: {', '.join(missing)}")

            df = df.copy()
            df["nom"] = df["nom"].fillna("").astype(str)
            df["categorie"] = df["categorie"].fillna("").astype(str)
            df["marque"] = df["marque"].fillna("").astype(str)
            df["description"] = df["description"].fillna("").astype(str)
            df["est_actif"] = df["est_actif"].fillna(True).astype(bool)
            df["product_id"] = df["product_id"].astype(int)
            df["document"] = df.apply(self._row_to_document, axis=1)

            vectors = self._model.encode(
                df["document"].tolist(),
                convert_to_numpy=True,
                normalize_embeddings=True,
                show_progress_bar=False,
            )

            self._df = df.reset_index(drop=True)
            self._embeddings = vectors.astype(np.float32)
            self._csv_path = target

            return {
                "status": "ok",
                "csv_path": str(self._csv_path),
                "indexed_products": int(len(self._df)),
                "model": self._model_name,
            }

    def search(self, request: SearchRequest) -> dict[str, Any]:
        with self._lock:
            if self._embeddings is None or self._df.empty:
                return {"query": request.query, "results": [], "product_ids": []}

            query_text = request.query.strip()
            if request.semantic_keywords:
                query_text += " " + " ".join(k.strip() for k in request.semantic_keywords if k.strip())

            query_vec = self._model.encode(
                [query_text],
                convert_to_numpy=True,
                normalize_embeddings=True,
                show_progress_bar=False,
            )[0].astype(np.float32)

            scores = self._embeddings @ query_vec
            ranked_indices = np.argsort(-scores)
            rows: list[dict[str, Any]] = []

            for idx in ranked_indices:
                row = self._df.iloc[int(idx)]
                if request.active_only and not bool(row["est_actif"]):
                    continue
                if request.category and str(row["categorie"]).lower() != request.category.lower():
                    continue

                rows.append(
                    {
                        "product_id": int(row["product_id"]),
                        "score": float(scores[int(idx)]),
                        "nom": str(row["nom"]),
                        "categorie": str(row["categorie"]),
                        "marque": str(row["marque"]),
                    }
                )
                if len(rows) >= request.limit:
                    break

            return {
                "query": request.query,
                "results": rows,
                "product_ids": [r["product_id"] for r in rows],
                "indexed_products": int(len(self._df)),
            }

    @staticmethod
    def _row_to_document(row: pd.Series) -> str:
        return " | ".join(
            [
                f"nom: {row['nom']}",
                f"categorie: {row['categorie']}",
                f"marque: {row['marque']}",
                f"description: {row['description']}",
            ]
        )


app = FastAPI(title="Parapharmacie Semantic Search API", version="1.0.0")
engine: SemanticSearchEngine | None = None


@app.on_event("startup")
def startup_event() -> None:
    global engine
    csv_path = Path(__import__("os").environ.get("SEMANTIC_PRODUCTS_CSV", str(DEFAULT_CSV)))
    model_name = __import__("os").environ.get("SEMANTIC_MODEL_NAME", DEFAULT_MODEL)
    engine = SemanticSearchEngine(model_name=model_name, csv_path=csv_path)


@app.get("/health")
def health() -> dict[str, Any]:
    if engine is None:
        raise HTTPException(status_code=503, detail="Engine is not initialized")
    return {
        "status": "ok",
        "csv_path": str(engine._csv_path),  # noqa: SLF001
        "indexed_products": int(len(engine._df)),  # noqa: SLF001
    }


@app.post("/reindex")
def reindex(payload: ReindexRequest) -> dict[str, Any]:
    if engine is None:
        raise HTTPException(status_code=503, detail="Engine is not initialized")
    try:
        csv_path = Path(payload.csv_path) if payload.csv_path else None
        return engine.reindex(csv_path=csv_path)
    except (FileNotFoundError, ValueError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc


@app.post("/search")
def search(payload: SearchRequest) -> dict[str, Any]:
    if engine is None:
        raise HTTPException(status_code=503, detail="Engine is not initialized")
    return engine.search(payload)
