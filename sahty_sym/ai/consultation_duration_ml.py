import argparse
import unicodedata

import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.impute import SimpleImputer
from sklearn.linear_model import LinearRegression
from sklearn.metrics import mean_absolute_error, r2_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler

TARGET_COL = "duree_consultation_min"
DATA_DEFAULT = "consultation_dataset.csv"

# Variables disponibles avant la consultation (pas de fuite de donnees)
PRE_VISIT_FEATURES = [
    "motif_rendez_vous",
    "antecedents",
    "allergies",
    "traitement_en_cours",
    "taille",
    "poids",
    "imc",
    "categorie_imc",
]


MOTIF_ALIASES = {
    "controle": "controle",
    "control": "controle",
    "controle_general": "controle",
    "suivi diabete": "suivi_diabete",
    "suivi_diabete": "suivi_diabete",
    "suivi hypertension": "suivi_hypertension",
    "suivi_hypertension": "suivi_hypertension",
    "renouvellement": "renouvellement",
    "infection": "infection",
    "urgence": "urgence",
    "bilan annuel": "bilan_annuel",
    "bilan_annuel": "bilan_annuel",
}


def load_data(path: str) -> pd.DataFrame:
    df = pd.read_csv(path)
    required_cols = set(PRE_VISIT_FEATURES + [TARGET_COL])
    missing = required_cols.difference(df.columns)
    if missing:
        raise ValueError(f"Colonnes manquantes dans le CSV: {sorted(missing)}")
    return df


def build_preprocessor(categorical_cols, numeric_cols):
    cat_pipe = Pipeline(
        steps=[
            ("imputer", SimpleImputer(strategy="most_frequent")),
            ("onehot", OneHotEncoder(handle_unknown="ignore")),
        ]
    )

    num_pipe = Pipeline(
        steps=[
            ("imputer", SimpleImputer(strategy="median")),
            ("scaler", StandardScaler()),
        ]
    )

    return ColumnTransformer(
        transformers=[
            ("cat", cat_pipe, categorical_cols),
            ("num", num_pipe, numeric_cols),
        ]
    )


def train_and_compare(df: pd.DataFrame):
    feature_cols = PRE_VISIT_FEATURES
    X = df[feature_cols]
    y = df[TARGET_COL]

    categorical_cols = [
        "motif_rendez_vous",
        "antecedents",
        "allergies",
        "traitement_en_cours",
        "categorie_imc",
    ]
    numeric_cols = ["taille", "poids", "imc"]

    preprocessor = build_preprocessor(categorical_cols, numeric_cols)

    models = {
        "LinearRegression": LinearRegression(),
        "RandomForest": RandomForestRegressor(n_estimators=300, random_state=42),
        "GradientBoosting": GradientBoostingRegressor(random_state=42),
    }

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42
    )

    results = []
    fitted = {}

    for name, model in models.items():
        pipe = Pipeline(steps=[("prep", preprocessor), ("model", model)])
        pipe.fit(X_train, y_train)
        pred = pipe.predict(X_test)
        mae = mean_absolute_error(y_test, pred)
        r2 = r2_score(y_test, pred)
        results.append((name, mae, r2))
        fitted[name] = pipe

    results.sort(key=lambda x: x[1])

    print("=== Comparaison des modeles (test set) ===")
    for name, mae, r2 in results:
        print(f"{name:18s} | MAE: {mae:6.2f} min | R2: {r2:6.3f}")

    best_name = results[0][0]
    best_model = fitted[best_name]
    print(f"\nMeilleur modele: {best_name}")

    return best_model, feature_cols


def strip_accents(text: str) -> str:
    normalized = unicodedata.normalize("NFKD", text)
    return "".join(ch for ch in normalized if not unicodedata.combining(ch))


def normalize_text(value: str, default_value: str = "aucun") -> str:
    if value is None:
        return default_value
    txt = str(value).strip()
    if not txt:
        return default_value
    txt = strip_accents(txt).lower()
    txt = " ".join(txt.split())
    return txt


def parse_float(value: str, field_name: str) -> float:
    try:
        return float(value.replace(",", ".").strip())
    except Exception as exc:
        raise ValueError(f"Valeur invalide pour {field_name}: {value}") from exc


def imc_category(imc: float) -> str:
    if imc < 18.5:
        return "Insuffisance"
    if imc < 25:
        return "Normal"
    if imc < 30:
        return "Surpoids"
    return "Obese"


def normalize_motif(motif: str) -> str:
    normalized = normalize_text(motif, "controle")
    normalized = normalized.replace("-", " ")
    normalized = MOTIF_ALIASES.get(normalized, normalized)
    return normalized.replace(" ", "_")


def build_patient_row(raw: dict) -> dict:
    taille = parse_float(raw["taille"], "taille")
    poids = parse_float(raw["poids"], "poids")

    if taille <= 0 or poids <= 0:
        raise ValueError("taille et poids doivent etre > 0")

    imc_input = str(raw.get("imc", "")).strip()
    if imc_input:
        imc_value = parse_float(imc_input, "imc")
    else:
        imc_value = poids / (taille * taille)

    categorie_input = str(raw.get("categorie_imc", "")).strip()
    if categorie_input:
        categorie_value = normalize_text(categorie_input, imc_category(imc_value)).capitalize()
    else:
        categorie_value = imc_category(imc_value)

    return {
        "motif_rendez_vous": normalize_motif(raw.get("motif_rendez_vous", "controle")),
        "antecedents": normalize_text(raw.get("antecedents", "aucun")),
        "allergies": normalize_text(raw.get("allergies", "aucune")),
        "traitement_en_cours": normalize_text(raw.get("traitement_en_cours", "rien")),
        "taille": taille,
        "poids": poids,
        "imc": round(imc_value, 2),
        "categorie_imc": categorie_value,
    }


def predict_from_row(model, feature_cols, row):
    df_one = pd.DataFrame([row])[feature_cols]
    pred = model.predict(df_one)[0]
    print("\nDonnees patient normalisees:")
    for key in PRE_VISIT_FEATURES:
        print(f"- {key}: {row[key]}")
    print(f"\nDuree predite: {pred:.1f} minutes")


def predict_one(model, feature_cols, args):
    row = build_patient_row(
        {
            "motif_rendez_vous": args.motif,
            "antecedents": args.antecedents,
            "allergies": args.allergies,
            "traitement_en_cours": args.traitement_en_cours,
            "taille": str(args.taille),
            "poids": str(args.poids),
            "imc": str(args.imc) if args.imc is not None else "",
            "categorie_imc": args.categorie_imc or "",
        }
    )
    predict_from_row(model, feature_cols, row)


def predict_from_patient_input(model, feature_cols):
    print("\n=== Saisie patient ===")
    raw = {
        "motif_rendez_vous": input("Motif rendez-vous: "),
        "antecedents": input("Antecedents (laisser vide si aucun): "),
        "allergies": input("Allergies (laisser vide si aucune): "),
        "traitement_en_cours": input("Traitement en cours (laisser vide si rien): "),
        "taille": input("Taille en metres (ex: 1.57): "),
        "poids": input("Poids en kg (ex: 50): "),
        "imc": input("IMC (laisser vide pour calcul auto): "),
        "categorie_imc": input("Categorie IMC (laisser vide pour auto): "),
    }

    row = build_patient_row(raw)
    predict_from_row(model, feature_cols, row)


def parser_args():
    parser = argparse.ArgumentParser(
        description="Prediction de duree de consultation (motif + fiche medicale pre-consultation)."
    )
    parser.add_argument("--data", default=DATA_DEFAULT, help="Chemin du CSV")

    parser.add_argument("--predict", action="store_true", help="Faire une prediction unique")
    parser.add_argument("--patient_input", action="store_true", help="Saisie interactive des donnees patient")
    parser.add_argument("--motif", default="controle")
    parser.add_argument("--antecedents", default="aucun")
    parser.add_argument("--allergies", default="aucune")
    parser.add_argument("--traitement_en_cours", default="aucun")
    parser.add_argument("--taille", default="1.70")
    parser.add_argument("--poids", default="70.0")
    parser.add_argument("--imc", default="")
    parser.add_argument("--categorie_imc", default="")
    return parser.parse_args()


def main():
    args = parser_args()
    df = load_data(args.data)
    model, feature_cols = train_and_compare(df)

    if args.patient_input:
        predict_from_patient_input(model, feature_cols)
    elif args.predict:
        predict_one(model, feature_cols, args)
    else:
        print("\nAstuce: ajoute --predict ou --patient_input pour predire un cas patient.")


if __name__ == "__main__":
    main()
