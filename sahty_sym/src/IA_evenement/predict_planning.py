import json
import math
import sys
from pathlib import Path

import joblib
import pandas as pd


def error(message: str, code: int = 1):
    payload = {"ok": False, "message": message}
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(code)


def load_json_arg() -> dict:
    if len(sys.argv) < 2:
        error("Argument JSON manquant.")
    try:
        return json.loads(sys.argv[1])
    except Exception:
        error("JSON d'entrée invalide.")


def load_phase_stats(path: Path) -> dict:
    if not path.exists():
        error("Fichier phase_stats.json introuvable. Lancez d'abord train_model.py")
    return json.loads(path.read_text(encoding="utf-8"))


def find_ratio(phase: str, event_type: str, mode: str, phase_stats: dict) -> float:
    for row in phase_stats.get("by_type_mode", []):
        if row["event_type"] == event_type and row["mode"] == mode and row["phase_label"] == phase:
            return float(row["ratio"])
    for row in phase_stats.get("by_type", []):
        if row["event_type"] == event_type and row["phase_label"] == phase:
            return float(row["ratio"])
    for row in phase_stats.get("global", []):
        if row["phase_label"] == phase:
            return float(row["ratio"])
    return 0.16


def allocate_durations(sequence: list[str], duration_total: int, event_type: str, mode: str, phase_stats: dict) -> list[int]:
    ratios = [max(0.04, find_ratio(p, event_type, mode, phase_stats)) for p in sequence]
    ratio_sum = sum(ratios) or 1.0
    raw = [duration_total * (r / ratio_sum) for r in ratios]
    rounded = [max(8, int(round(x))) for x in raw]

    delta = duration_total - sum(rounded)
    i = 0
    while delta != 0 and len(rounded) > 0:
        idx = i % len(rounded)
        if delta > 0:
            rounded[idx] += 1
            delta -= 1
        elif rounded[idx] > 8:
            rounded[idx] -= 1
            delta += 1
        i += 1
        if i > 20000:
            break
    return rounded


def main():
    payload = load_json_arg()

    base_dir = Path(__file__).resolve().parent
    model_path = base_dir / "models" / "planning_model.joblib"
    phase_stats_path = base_dir / "models" / "phase_stats.json"

    if not model_path.exists():
        error("Modèle introuvable. Lancez d'abord: python train_model.py")

    event_type = str(payload.get("event_type", "formation") or "formation")
    mode = str(payload.get("mode", "presentiel") or "presentiel")
    audience = str(payload.get("audience", "mixte") or "mixte")
    level = str(payload.get("level", "intermediaire") or "intermediaire")

    try:
        duration_total = int(payload.get("duration_total_min", 180))
    except Exception:
        duration_total = 180
    duration_total = max(60, min(duration_total, 720))

    model = joblib.load(model_path)
    phase_stats = load_phase_stats(phase_stats_path)

    x = pd.DataFrame(
        [
            {
                "event_type": event_type,
                "mode": mode,
                "audience": audience,
                "level": level,
                "duration_total_min": duration_total,
            }
        ]
    )

    pred_sequence = model.predict(x)[0]
    proba = None
    if hasattr(model, "predict_proba"):
        p = model.predict_proba(x)
        if isinstance(p, list):
            proba = None
        else:
            proba = float(p.max())

    phases = [s for s in str(pred_sequence).split(">") if s]
    if len(phases) < 3:
        phases = ["ouverture", "intervention", "pause", "atelier", "cloture"]

    durations = allocate_durations(phases, duration_total, event_type, mode, phase_stats)

    result = {
        "ok": True,
        "input": {
            "event_type": event_type,
            "mode": mode,
            "audience": audience,
            "level": level,
            "duration_total_min": duration_total,
        },
        "confidence": None if proba is None else round(proba, 3),
        "sequence": phases,
        "phases": [
            {
                "ordre": i + 1,
                "phase_label": phase,
                "duree_min": int(durations[i]),
            }
            for i, phase in enumerate(phases)
        ],
    }

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
