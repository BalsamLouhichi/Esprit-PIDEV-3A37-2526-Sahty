import argparse
import json
from pathlib import Path

import joblib
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import RandomForestClassifier
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder


def build_event_level(rows: pd.DataFrame) -> pd.DataFrame:
    rows = rows.sort_values(["event_id", "phase_order"])

    def agg(group: pd.DataFrame) -> pd.Series:
        seq = ">".join(group["phase_label"].astype(str).tolist())
        return pd.Series(
            {
                "event_type": group["event_type"].iloc[0],
                "mode": group["mode"].iloc[0],
                "audience": group["audience"].iloc[0],
                "level": group["level"].iloc[0],
                "duration_total_min": int(group["duration_total_min"].iloc[0]),
                "target_sequence": seq,
            }
        )

    return rows.groupby("event_id", as_index=False).apply(agg).reset_index(drop=True)


def build_phase_stats(rows: pd.DataFrame) -> dict:
    rows = rows.copy()
    rows["ratio"] = rows["phase_duration_min"] / rows["duration_total_min"].clip(lower=1)

    key_fields = ["event_type", "mode", "phase_label"]
    stats = (
        rows.groupby(key_fields, as_index=False)["ratio"]
        .median()
        .sort_values(key_fields)
    )

    by_type = (
        rows.groupby(["event_type", "phase_label"], as_index=False)["ratio"]
        .median()
        .sort_values(["event_type", "phase_label"])
    )

    global_stats = (
        rows.groupby(["phase_label"], as_index=False)["ratio"]
        .median()
        .sort_values(["phase_label"])
    )

    return {
        "by_type_mode": stats.to_dict(orient="records"),
        "by_type": by_type.to_dict(orient="records"),
        "global": global_stats.to_dict(orient="records"),
    }


def train(dataset_path: Path, output_dir: Path) -> None:
    rows = pd.read_csv(dataset_path)

    required = {
        "event_id",
        "event_type",
        "mode",
        "audience",
        "level",
        "duration_total_min",
        "phase_order",
        "phase_label",
        "phase_duration_min",
    }
    missing = required.difference(set(rows.columns))
    if missing:
        raise ValueError(f"Colonnes manquantes dans le dataset: {sorted(missing)}")

    events = build_event_level(rows)

    feature_cols = ["event_type", "mode", "audience", "level", "duration_total_min"]
    x = events[feature_cols]
    y = events["target_sequence"]

    categorical = ["event_type", "mode", "audience", "level"]
    numeric = ["duration_total_min"]

    preprocessor = ColumnTransformer(
        transformers=[
            ("cat", OneHotEncoder(handle_unknown="ignore"), categorical),
            ("num", "passthrough", numeric),
        ]
    )

    model = RandomForestClassifier(
        n_estimators=80,
        max_depth=12,
        min_samples_split=4,
        random_state=42,
        n_jobs=-1,
    )

    pipeline = Pipeline(
        steps=[
            ("prep", preprocessor),
            ("clf", model),
        ]
    )

    pipeline.fit(x, y)

    output_dir.mkdir(parents=True, exist_ok=True)
    model_path = output_dir / "planning_model.joblib"
    joblib.dump(pipeline, model_path)

    phase_stats = build_phase_stats(rows)
    phase_stats_path = output_dir / "phase_stats.json"
    phase_stats_path.write_text(json.dumps(phase_stats, ensure_ascii=False, indent=2), encoding="utf-8")

    metadata = {
        "rows_count": int(len(rows)),
        "events_count": int(len(events)),
        "features": feature_cols,
        "target": "target_sequence",
        "model": "RandomForestClassifier",
        "model_path": str(model_path),
        "phase_stats_path": str(phase_stats_path),
    }
    (output_dir / "metadata.json").write_text(json.dumps(metadata, ensure_ascii=False, indent=2), encoding="utf-8")

    print("[OK] Modèle entraîné")
    print(json.dumps(metadata, ensure_ascii=False, indent=2))


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Entraînement modèle planning événements")
    parser.add_argument(
        "--dataset",
        default="event_planning_dataset.csv",
        help="Chemin vers le dataset CSV",
    )
    parser.add_argument(
        "--out",
        default="models",
        help="Dossier de sortie des artefacts modèle",
    )
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()
    train(Path(args.dataset), Path(args.out))
