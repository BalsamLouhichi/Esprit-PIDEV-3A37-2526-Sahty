import csv
import os
import re
from collections import Counter, defaultdict
from dataclasses import dataclass
from pathlib import Path


@dataclass
class DatasetMeta:
    loaded: bool
    path: str = ""
    rows: int = 0
    tests: int = 0
    columns: list[str] | None = None
    message: str = ""


def _normalize_name(value: str) -> str:
    return re.sub(r"[^a-z0-9]", "", value.lower())


def _pick_column(columns: list[str], candidates: list[str]) -> str | None:
    normalized = {_normalize_name(c): c for c in columns}
    for c in candidates:
        key = _normalize_name(c)
        if key in normalized:
            return normalized[key]
    return None


def _parse_float(value: str) -> float | None:
    if value is None:
        return None
    text = str(value).strip().replace(",", ".")
    if not text:
        return None
    match = re.search(r"[-+]?\d*\.?\d+", text)
    if not match:
        return None
    try:
        return float(match.group(0))
    except ValueError:
        return None


def _percentile(sorted_values: list[float], q: float) -> float:
    if not sorted_values:
        return 0.0
    if len(sorted_values) == 1:
        return sorted_values[0]
    pos = (len(sorted_values) - 1) * q
    lo = int(pos)
    hi = min(lo + 1, len(sorted_values) - 1)
    frac = pos - lo
    return sorted_values[lo] * (1.0 - frac) + sorted_values[hi] * frac


class DomainContextService:
    def __init__(self) -> None:
        self.meta = DatasetMeta(loaded=False, message="No dataset loaded.")
        self._context_text = ""
        self._preview_lines: list[str] = []

    def load_from_path(self, dataset_path: str, max_tests: int = 50) -> DatasetMeta:
        path = Path(dataset_path)
        if not path.exists():
            self.meta = DatasetMeta(
                loaded=False,
                path=str(path),
                message="Dataset path not found.",
            )
            self._context_text = ""
            self._preview_lines = []
            return self.meta

        if path.suffix.lower() not in {".csv", ".tsv"}:
            self.meta = DatasetMeta(
                loaded=False,
                path=str(path),
                message="Only CSV/TSV datasets are supported.",
            )
            self._context_text = ""
            self._preview_lines = []
            return self.meta

        delimiter = "\t" if path.suffix.lower() == ".tsv" else ","
        with path.open("r", encoding="utf-8-sig", newline="") as f:
            reader = csv.DictReader(f, delimiter=delimiter)
            rows = list(reader)
            columns = reader.fieldnames or []

        if not rows or not columns:
            self.meta = DatasetMeta(
                loaded=False,
                path=str(path),
                message="Dataset is empty or invalid header.",
            )
            self._context_text = ""
            self._preview_lines = []
            return self.meta

        test_col = _pick_column(
            columns,
            [
                "test_name",
                "test",
                "analyte",
                "parameter",
                "biomarker",
                "investigation",
                "lab_test",
                "name",
            ],
        )
        value_col = _pick_column(columns, ["result", "value", "test_result", "measurement"])
        unit_col = _pick_column(columns, ["unit", "units", "measurement_unit"])
        status_col = _pick_column(columns, ["status", "flag", "interpretation", "abnormal_flag"])

        if not test_col:
            self.meta = DatasetMeta(
                loaded=False,
                path=str(path),
                rows=len(rows),
                columns=columns,
                message="Could not detect test name column.",
            )
            self._context_text = ""
            self._preview_lines = []
            return self.meta

        grouped_values: dict[str, list[float]] = defaultdict(list)
        grouped_units: dict[str, Counter[str]] = defaultdict(Counter)
        grouped_status: dict[str, Counter[str]] = defaultdict(Counter)
        grouped_count: Counter[str] = Counter()

        for row in rows:
            test_name = str(row.get(test_col, "")).strip()
            if not test_name:
                continue
            grouped_count[test_name] += 1

            if unit_col:
                unit = str(row.get(unit_col, "")).strip()
                if unit:
                    grouped_units[test_name][unit] += 1

            if value_col:
                value = _parse_float(str(row.get(value_col, "")))
                if value is not None:
                    grouped_values[test_name].append(value)

            if status_col:
                status = str(row.get(status_col, "")).strip().lower()
                if status:
                    grouped_status[test_name][status] += 1

        top_tests = [name for name, _ in grouped_count.most_common(max_tests)]
        lines: list[str] = []
        preview_lines: list[str] = []

        for test_name in top_tests:
            n = grouped_count[test_name]
            units = grouped_units.get(test_name)
            unit = units.most_common(1)[0][0] if units else ""

            values = sorted(grouped_values.get(test_name, []))
            p5 = p50 = p95 = None
            if len(values) >= 5:
                p5 = _percentile(values, 0.05)
                p50 = _percentile(values, 0.50)
                p95 = _percentile(values, 0.95)

            abnormal_rate = ""
            statuses = grouped_status.get(test_name, Counter())
            if statuses:
                abnormal_count = 0
                total_status = sum(statuses.values())
                for st, cnt in statuses.items():
                    if any(k in st for k in ["abnormal", "high", "low", "out", "critical", "alert"]):
                        abnormal_count += cnt
                if total_status > 0:
                    abnormal_rate = f"{(100.0 * abnormal_count / total_status):.1f}%"

            stat_text = f"{test_name} (n={n}"
            if unit:
                stat_text += f", unit={unit}"
            stat_text += ")"
            if p5 is not None and p95 is not None and p50 is not None:
                stat_text += f": p5-p95={p5:.2f}-{p95:.2f}, median={p50:.2f}"
            if abnormal_rate:
                stat_text += f", abnormal_rate={abnormal_rate}"

            line = f"- {stat_text}"
            lines.append(line)
            if len(preview_lines) < 12:
                preview_lines.append(line)

        header = (
            "Domain dataset context (anonymized laboratory dataset statistics):\n"
            "Use these ranges as soft guidance only.\n"
        )
        self._context_text = header + "\n".join(lines)
        self._preview_lines = preview_lines
        self.meta = DatasetMeta(
            loaded=True,
            path=str(path),
            rows=len(rows),
            tests=len(grouped_count),
            columns=columns,
            message="Dataset loaded.",
        )
        return self.meta

    def auto_load_from_env(self) -> DatasetMeta:
        dataset_path = os.getenv("DOMAIN_DATASET_PATH", "").strip()
        if not dataset_path:
            self.meta = DatasetMeta(loaded=False, message="DOMAIN_DATASET_PATH not set.")
            self._context_text = ""
            self._preview_lines = []
            return self.meta
        max_tests = int(os.getenv("DOMAIN_MAX_TESTS", "50"))
        return self.load_from_path(dataset_path, max_tests=max_tests)

    def get_context_text(self) -> str:
        return self._context_text

    def get_status_payload(self) -> dict:
        return {
            "loaded": self.meta.loaded,
            "path": self.meta.path,
            "rows": self.meta.rows,
            "tests": self.meta.tests,
            "columns": self.meta.columns or [],
            "message": self.meta.message,
            "preview": self._preview_lines,
        }
