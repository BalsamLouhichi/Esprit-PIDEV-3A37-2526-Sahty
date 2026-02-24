#!/usr/bin/env python3
"""
Fraud detection and user churn prediction using ML on a CSV dataset.

Usage:
  python fraud_user_ml.py --data dataset.csv
  python fraud_user_ml.py --generate --rows 1000 --out dataset.csv
"""

from __future__ import annotations

import argparse
from pathlib import Path
import sys

import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder
from sklearn.impute import SimpleImputer
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, roc_auc_score
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
import joblib

TARGET_FRAUD = "is_fraud"
TARGET_CHURN = "is_churn"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Fraud detection and user churn prediction from a CSV dataset."
    )
    parser.add_argument("--data", type=Path, default=None, help="Path to dataset CSV")
    parser.add_argument("--generate", action="store_true", help="Generate synthetic dataset")
    parser.add_argument("--rows", type=int, default=1000, help="Rows to generate")
    parser.add_argument("--out", type=Path, default=None, help="Output CSV path for generated dataset")
    parser.add_argument("--test-size", type=float, default=0.2, help="Test split ratio")
    parser.add_argument("--seed", type=int, default=42, help="Random seed")
    parser.add_argument("--save-models", action="store_true", help="Save trained models")
    parser.add_argument(
        "--pred-dir",
        type=Path,
        default=None,
        help="Directory to save predictions (and models if enabled)",
    )
    return parser.parse_args()


def generate_synthetic_dataset(rows: int, seed: int) -> pd.DataFrame:
    rng = np.random.default_rng(seed)

    user_id = np.arange(1, rows + 1)
    role = rng.choice(
        ["patient", "medecin", "laboratoire", "parapharmacie"],
        size=rows,
        p=[0.7, 0.1, 0.1, 0.1],
    )
    region = rng.choice(
        ["tunis", "nabeul", "sfax", "sousse", "bizerte"],
        size=rows,
        p=[0.35, 0.15, 0.2, 0.2, 0.1],
    )
    device_type = rng.choice(
        ["android", "ios", "web", "desktop"],
        size=rows,
        p=[0.45, 0.25, 0.2, 0.1],
    )

    account_age_days = rng.integers(30, 2000, size=rows)
    last_activity_days = np.clip(rng.normal(25, 30, size=rows), 0, 180).astype(int)
    activity_scale = np.clip(rng.normal(1.0, 0.6, size=rows), 0.1, 3.0)

    demands_30d = rng.poisson(lam=3 * activity_scale)
    appointments_30d = rng.poisson(lam=2 * activity_scale)

    base_cancel = rng.beta(2, 8, size=rows)
    cancellations_30d = rng.binomial(appointments_30d, base_cancel)
    cancel_rate_30d = np.where(
        appointments_30d > 0, cancellations_30d / appointments_30d, 0.0
    )

    abnormal_night_actions_30d = rng.poisson(lam=0.4 + 0.3 * (activity_scale > 1.5))
    shared_phone_count = rng.choice([0, 1, 2, 3], size=rows, p=[0.78, 0.14, 0.06, 0.02])
    payment_failures_30d = rng.poisson(lam=0.4 + 0.15 * shared_phone_count)
    chargebacks_30d = rng.poisson(lam=0.15 + 0.1 * shared_phone_count)
    avg_payment_amount_30d = np.round(
        np.clip(rng.normal(60, 35, size=rows), 5, 400), 2
    )

    activity_30d = demands_30d + appointments_30d

    fraud_score = (
        1.2 * chargebacks_30d
        + 0.8 * payment_failures_30d
        + 0.6 * shared_phone_count
        + 0.4 * abnormal_night_actions_30d
        + 0.3 * (cancel_rate_30d * 10)
        + 0.2 * (activity_30d / 5.0)
    )
    fraud_prob = 1.0 / (1.0 + np.exp(-(fraud_score - 3.0)))
    is_fraud = rng.binomial(1, np.clip(fraud_prob, 0.0, 1.0))

    churn_score = (
        0.04 * last_activity_days
        - 0.08 * activity_30d
        + 0.6 * (cancel_rate_30d * 10)
    )
    churn_prob = 1.0 / (1.0 + np.exp(-(churn_score - 1.0)))
    is_churn = rng.binomial(1, np.clip(churn_prob, 0.0, 1.0))

    return pd.DataFrame(
        {
            "user_id": user_id,
            "role": role,
            "region": region,
            "device_type": device_type,
            "account_age_days": account_age_days,
            "last_activity_days": last_activity_days,
            "demands_30d": demands_30d,
            "appointments_30d": appointments_30d,
            "cancellations_30d": cancellations_30d,
            "cancel_rate_30d": np.round(cancel_rate_30d, 4),
            "abnormal_night_actions_30d": abnormal_night_actions_30d,
            "shared_phone_count": shared_phone_count,
            "payment_failures_30d": payment_failures_30d,
            "chargebacks_30d": chargebacks_30d,
            "avg_payment_amount_30d": avg_payment_amount_30d,
            "is_fraud": is_fraud,
            "is_churn": is_churn,
        }
    )


def build_preprocess(x: pd.DataFrame) -> ColumnTransformer:
    cat_cols = x.select_dtypes(include=["object", "category", "bool"]).columns.tolist()
    num_cols = [c for c in x.columns if c not in cat_cols]

    transformers = []
    if num_cols:
        transformers.append(("num", SimpleImputer(strategy="median"), num_cols))
    if cat_cols:
        transformers.append(
            (
                "cat",
                Pipeline(
                    [
                        ("imputer", SimpleImputer(strategy="most_frequent")),
                        ("onehot", OneHotEncoder(handle_unknown="ignore")),
                    ]
                ),
                cat_cols,
            )
        )

    if not transformers:
        raise ValueError("No feature columns found.")

    return ColumnTransformer(transformers)


def train_and_report(
    name: str,
    df: pd.DataFrame,
    target_col: str,
    drop_cols: list[str],
    model,
    test_size: float,
    seed: int,
):
    x = df.drop(columns=drop_cols, errors="ignore")
    y = df[target_col].astype(int)

    if y.nunique() < 2:
        print(f"{name}: only one class in target, skipping.")
        return None

    stratify = y if y.value_counts().min() >= 2 else None
    x_train, x_test, y_train, y_test = train_test_split(
        x, y, test_size=test_size, random_state=seed, stratify=stratify
    )

    preprocess = build_preprocess(x_train)
    pipe = Pipeline([("preprocess", preprocess), ("model", model)])

    pipe.fit(x_train, y_train)
    y_pred = pipe.predict(x_test)

    print(f"\n=== {name} ===")
    print(f"Rows: {len(df)}  Positives: {int(y.sum())}  Rate: {y.mean():.3f}")
    print(classification_report(y_test, y_pred, digits=3))

    if hasattr(pipe, "predict_proba"):
        try:
            y_proba = pipe.predict_proba(x_test)[:, 1]
            print(f"ROC AUC: {roc_auc_score(y_test, y_proba):.3f}")
        except ValueError:
            pass

    pipe.fit(x, y)
    return pipe


def save_predictions(pipe, df: pd.DataFrame, drop_cols: list[str], out_path: Path) -> None:
    x = df.drop(columns=drop_cols, errors="ignore")
    out = pd.DataFrame()
    if "user_id" in df.columns:
        out["user_id"] = df["user_id"]

    out["prediction"] = pipe.predict(x)
    if hasattr(pipe, "predict_proba"):
        out["probability"] = pipe.predict_proba(x)[:, 1]

    out_path.parent.mkdir(parents=True, exist_ok=True)
    out.to_csv(out_path, index=False)


def main() -> int:
    args = parse_args()

    script_dir = Path(__file__).resolve().parent
    data_path = args.data if args.data else script_dir / "dataset.csv"

    if args.generate:
        out_path = args.out if args.out else data_path
        df = generate_synthetic_dataset(args.rows, args.seed)
        out_path.parent.mkdir(parents=True, exist_ok=True)
        df.to_csv(out_path, index=False)
        print(f"Wrote {len(df)} rows to {out_path}")
        return 0

    if not data_path.exists():
        print(f"Dataset not found: {data_path}. Use --generate to create one.", file=sys.stderr)
        return 1

    df = pd.read_csv(data_path)

    missing = [c for c in [TARGET_FRAUD, TARGET_CHURN] if c not in df.columns]
    if missing:
        print(f"Missing required columns: {', '.join(missing)}", file=sys.stderr)
        return 1

    drop_cols = [TARGET_FRAUD, TARGET_CHURN, "user_id", "user_name"]

    fraud_model = RandomForestClassifier(
        n_estimators=250,
        max_depth=None,
        class_weight="balanced",
        random_state=args.seed,
        n_jobs=-1,
    )
    churn_model = LogisticRegression(
        max_iter=500,
        class_weight="balanced",
        solver="liblinear",
    )

    fraud_pipe = train_and_report(
        "Fraud detection",
        df,
        TARGET_FRAUD,
        drop_cols,
        fraud_model,
        args.test_size,
        args.seed,
    )
    churn_pipe = train_and_report(
        "User churn prediction",
        df,
        TARGET_CHURN,
        drop_cols,
        churn_model,
        args.test_size,
        args.seed,
    )

    pred_dir = args.pred_dir if args.pred_dir else data_path.parent
    pred_dir.mkdir(parents=True, exist_ok=True)

    if fraud_pipe is not None:
        save_predictions(fraud_pipe, df, drop_cols, pred_dir / "predictions_fraud.csv")
        if args.save_models:
            joblib.dump(fraud_pipe, pred_dir / "model_fraud.joblib")

    if churn_pipe is not None:
        save_predictions(churn_pipe, df, drop_cols, pred_dir / "predictions_churn.csv")
        if args.save_models:
            joblib.dump(churn_pipe, pred_dir / "model_churn.joblib")

    print(f"Predictions saved to: {pred_dir}")
    if args.save_models:
        print(f"Models saved to: {pred_dir}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
