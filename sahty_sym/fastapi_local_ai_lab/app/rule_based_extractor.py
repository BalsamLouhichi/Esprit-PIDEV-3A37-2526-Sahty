import re
import unicodedata
from dataclasses import dataclass

from .schemas import ExtractedTest, StructuredExtraction


@dataclass(frozen=True)
class _TestSpec:
    name: str
    patterns: tuple[str, ...]


TEST_SPECS: tuple[_TestSpec, ...] = (
    _TestSpec("CRP", ("crp", "c reactive protein")),
    _TestSpec("Hematies", ("hematies", "numeration globulaire")),
    _TestSpec("Hemoglobine", ("hemoglobine", "hb")),
    _TestSpec("Hematocrite", ("hematocrite",)),
    _TestSpec("VGM", ("vgm",)),
    _TestSpec("TCMH", ("tcmh",)),
    _TestSpec("CCMH", ("ccmh",)),
    _TestSpec("Leucocytes", ("leucocytes", "leukocytes", "wbc")),
    _TestSpec("Polynucleaires neutrophiles", ("polynucleaires neutrophiles", "neutrophiles")),
    _TestSpec("Polynucleaires eosinophiles", ("eosinophiles", "polynucle")),
    _TestSpec("Polynucleaires basophiles", ("basophiles",)),
    _TestSpec("Lymphocytes", ("lymphocytes",)),
    _TestSpec("Monocytes", ("monocytes",)),
    _TestSpec("Numeration des plaquettes", ("plaquettes", "plt", "numeration des plaquettes")),
    _TestSpec("ALAT", ("alat", "alt")),
    _TestSpec("ASAT", ("asat", "ast")),
    _TestSpec("Creatinine", ("creatinine",)),
    _TestSpec("Glycemie a jeun", ("glycemie a jeun", "glycemie")),
    _TestSpec("HbA1c", ("hba1c",)),
    _TestSpec("Vitesse de sedimentation 1ere heure", ("vitesse de sedimentation", "1 heure", "1° heure")),
)


def _normalize_text(value: str) -> str:
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    value = value.lower()
    value = value.replace("...", " ").replace("..", " ")
    value = re.sub(r"\s+", " ", value).strip()
    return value


def _parse_numeric(raw: str | None) -> float | None:
    if not raw:
        return None
    text = raw.strip().replace(",", ".")
    if text.startswith("."):
        return None
    text = text.replace(" ", "")
    match = re.search(r"[-+]?\d*\.?\d+", text)
    if not match:
        return None
    try:
        return float(match.group(0))
    except ValueError:
        return None


def _parse_reference(reference_text: str | None) -> tuple[float | None, float | None]:
    if not reference_text:
        return None, None

    text = _normalize_text(reference_text)
    nums = [n.replace(" ", "") for n in re.findall(r"\d[\d\s]*(?:[.,]\d+)?", text)]
    parsed: list[float] = []
    for n in nums:
        try:
            parsed.append(float(n.replace(",", ".")))
        except ValueError:
            continue

    if "<" in text and parsed:
        return None, parsed[-1]
    if ">" in text and parsed:
        return parsed[0], None
    if len(parsed) >= 2:
        return parsed[0], parsed[1]
    return None, None


def _compute_status(value_num: float | None, ref_low: float | None, ref_high: float | None) -> str:
    if value_num is None:
        return "UNKNOWN"
    if ref_low is None and ref_high is None:
        return "UNKNOWN"
    if ref_low is not None and value_num < ref_low:
        return "LOW"
    if ref_high is not None and value_num > ref_high:
        return "HIGH"
    return "NORMAL"


def _looks_like_reference(line: str) -> bool:
    norm = _normalize_text(line)
    if not norm:
        return False
    if re.search(r"\d[\d\s.,]*\s*(?:a|à|-)\s*\d[\d\s.,]*", norm):
        return True
    if re.search(r"[<>]\s*a?\s*\d[\d\s.,]*", norm):
        return True
    return False


def _extract_reference_lines(lines: list[str]) -> list[str]:
    refs: list[str] = []
    for line in lines:
        norm = _normalize_text(line)
        if not norm:
            continue
        if not _looks_like_reference(norm):
            continue
        m = re.search(r"(<\s*a?\s*\d[\d\s.,]*|\d[\d\s.,]*\s*(?:a|à|-)\s*\d[\d\s.,]*)", norm)
        if m:
            refs.append(m.group(1).replace("à", "a"))
    return refs


def _extract_unit_candidates(lines: list[str]) -> list[str]:
    units: list[str] = []
    start_idx = None
    for i, line in enumerate(lines):
        norm = _normalize_text(line)
        if norm in {"uni", "unit", "unite", "unites"} or norm.startswith("uni "):
            start_idx = i
            break

    if start_idx is None:
        return units

    for line in lines[start_idx + 1 :]:
        raw = line.strip()
        norm = _normalize_text(raw)
        if not norm:
            continue
        if norm == "flag" or norm.startswith("flag "):
            break
        if _looks_like_reference(norm):
            break
        if (
            "/" in raw
            or "%" in raw
            or "u/l" in norm
            or "g/dl" in norm
            or "g/100" in norm
            or "mg/" in norm
            or ("10" in norm and "l" in norm)
        ):
            units.append(raw)
    return units


def _extract_value_candidates(lines: list[str]) -> list[tuple[str, str | None, bool]]:
    values: list[tuple[str, str | None, bool]] = []
    for line in lines:
        raw = line.strip()
        norm = _normalize_text(raw)
        if not norm:
            continue
        if _looks_like_reference(norm):
            continue
        if any(token in norm for token in ("heure", "page", "valeurs de reference", "vr =", "date rendu")):
            continue

        m = re.match(r"^\s*([<>]?\s*[-+]?\d[\d\s.,]*|[<>]?\s*\.\d+)\s*([/%a-zA-Zµ*?0-9\-\s]+)?$", raw)
        if not m:
            continue

        value_raw = m.group(1).strip()
        unit = m.group(2).strip() if m.group(2) else None

        if unit and "/" not in unit and re.search(r"\b(heure|h)\b", _normalize_text(unit)):
            continue

        uncertain = ("?" in raw) or ("*" in raw) or value_raw.startswith(".")
        values.append((value_raw, unit, uncertain))
    return values


def _canonical_test_name(line: str) -> str | None:
    norm = _normalize_text(line)
    if not norm:
        return None
    for spec in TEST_SPECS:
        if any(pat in norm for pat in spec.patterns):
            return spec.name
    if re.search(r"[a-z]", norm) and len(norm) > 2:
        return line.strip()
    return None


def _extract_exam_section_tests(lines: list[str]) -> list[str]:
    start_idx = None
    for i, line in enumerate(lines):
        if _normalize_text(line) == "examen":
            start_idx = i
            break
    if start_idx is None:
        return []

    tests: list[str] = []
    seen: set[str] = set()
    for line in lines[start_idx + 1 :]:
        norm = _normalize_text(line)
        if not norm:
            continue
        if norm in {"uni", "unit", "unite", "flag"}:
            break
        if any(k in norm for k in ("patient", "date", "prescripteur", "rapport", "note test")):
            continue
        if re.match(r"^\s*[-+]?\d[\d\s.,]*\s*$", line.strip()):
            # values block likely started
            continue

        name = _canonical_test_name(line)
        if not name:
            continue
        key = _normalize_text(name)
        if key not in seen:
            tests.append(name)
            seen.add(key)
    return tests


def _extract_tests_in_order(lines: list[str]) -> list[str]:
    found: list[str] = []
    seen: set[str] = set()
    for line in lines:
        norm = _normalize_text(line)
        if not norm:
            continue
        for spec in TEST_SPECS:
            if spec.name in seen:
                continue
            if any(pat in norm for pat in spec.patterns):
                found.append(spec.name)
                seen.add(spec.name)
                break
    return found


def extract_hematology_structured(ocr_text: str) -> StructuredExtraction:
    lines = [l.strip() for l in ocr_text.splitlines() if l.strip()]

    tests_in_order = _extract_tests_in_order(lines)
    exam_tests = _extract_exam_section_tests(lines)
    if len(exam_tests) > len(tests_in_order):
        tests_in_order = exam_tests

    refs = _extract_reference_lines(lines)
    units = _extract_unit_candidates(lines)
    values = _extract_value_candidates(lines)

    warnings: list[str] = []
    if not tests_in_order:
        tests_in_order = [spec.name for spec in TEST_SPECS]
        warnings.append("No reliable test order found in OCR. Fallback order applied.")

    mapping_mismatch = len(values) != len(tests_in_order) or len(refs) not in {len(tests_in_order), len(tests_in_order) - 1}
    severe_mismatch = (
        len(values) != len(tests_in_order)
        or len(refs) < max(0, len(tests_in_order) - 1)
        or len(refs) > len(tests_in_order)
    )

    if len(values) < len(tests_in_order):
        warnings.append("Missing values for several tests (OCR incomplete).")
    if len(refs) < len(tests_in_order):
        warnings.append("Missing references for several tests (OCR incomplete).")
    if units and len(units) < len(tests_in_order):
        warnings.append("Missing units for several tests (OCR incomplete).")
    if mapping_mismatch:
        warnings.append("Test/value/reference counts mismatch. Mapping may require review.")

    ref_offset = 0
    if len(tests_in_order) > 0 and len(refs) == len(tests_in_order) - 1:
        first_test = _normalize_text(tests_in_order[0])
        if "crp" in first_test:
            ref_offset = 1

    unit_offset = 0
    if len(tests_in_order) > 0 and len(units) == len(tests_in_order) - 1:
        first_test = _normalize_text(tests_in_order[0])
        if "crp" in first_test:
            unit_offset = 1

    assigned_value_idx: list[int | None] = [None] * len(tests_in_order)
    used_values: set[int] = set()
    anchor_used: set[str] = set()

    if len(values) == len(tests_in_order):
        for i in range(len(tests_in_order)):
            assigned_value_idx[i] = i
    else:
        index_by_test = {name: i for i, name in enumerate(tests_in_order)}

        def set_anchor(test_name: str, matcher) -> None:
            test_idx = index_by_test.get(test_name)
            if test_idx is None:
                return
            for vi, (v_raw, v_unit, _unc) in enumerate(values):
                if vi in used_values:
                    continue
                if matcher(v_raw, v_unit):
                    assigned_value_idx[test_idx] = vi
                    used_values.add(vi)
                    anchor_used.add(test_name)
                    return

        set_anchor("Hematies", lambda v_raw, _u: (_parse_numeric(v_raw) is not None and _parse_numeric(v_raw) >= 1_000_000))
        set_anchor("Hemoglobine", lambda v_raw, _u: (_parse_numeric(v_raw) is not None and 5 <= _parse_numeric(v_raw) <= 20))
        set_anchor(
            "Numeration des plaquettes",
            lambda v_raw, _u: (_parse_numeric(v_raw) is not None and 100_000 <= _parse_numeric(v_raw) <= 1_000_000),
        )

        v_ptr = 0
        for ti in range(len(tests_in_order)):
            if assigned_value_idx[ti] is not None:
                continue
            while v_ptr < len(values) and v_ptr in used_values:
                v_ptr += 1
            if v_ptr < len(values):
                assigned_value_idx[ti] = v_ptr
                used_values.add(v_ptr)
                v_ptr += 1

    tests: list[ExtractedTest] = []
    for idx, test_name in enumerate(tests_in_order):
        value_raw = None
        unit = None
        uncertain_value = False

        vi = assigned_value_idx[idx]
        if vi is not None and 0 <= vi < len(values):
            value_raw, unit, uncertain_value = values[vi]

        if (unit is None or not unit.strip()) and units:
            ui = idx - unit_offset
            if 0 <= ui < len(units):
                unit = units[ui]

        ri = idx - ref_offset
        reference_text = refs[ri] if 0 <= ri < len(refs) else None
        ref_low, ref_high = _parse_reference(reference_text)
        value_num = _parse_numeric(value_raw)
        status = _compute_status(value_num, ref_low, ref_high)

        notes: list[str] = []
        needs_review = False
        if value_raw is None:
            notes.append("Missing value in OCR.")
            needs_review = True
        if reference_text is None:
            notes.append("Missing reference in OCR.")
            needs_review = True
        if value_raw is not None and uncertain_value:
            notes.append("Value/unit uncertain from OCR.")
            needs_review = True
        if status == "UNKNOWN":
            needs_review = True

        is_anchor = test_name in anchor_used
        if severe_mismatch and value_raw is not None and not is_anchor:
            reference_text = None
            ref_low = None
            ref_high = None
            status = "UNKNOWN"
            notes.append("Mapping uncertain due severe mismatch. Reference detached.")
            needs_review = True

        confidence = 0.3
        if value_raw and reference_text and unit and not uncertain_value and not severe_mismatch:
            confidence = 0.9
        elif value_raw and reference_text and not severe_mismatch:
            confidence = 0.6

        tests.append(
            ExtractedTest(
                name=test_name,
                value_raw=value_raw,
                value_num=value_num,
                unit=unit,
                reference_text=reference_text,
                ref_low=ref_low,
                ref_high=ref_high,
                status=status,
                needs_review=needs_review,
                confidence=confidence,
                notes=notes,
            )
        )

    global_review = any(t.needs_review for t in tests)
    ntext = _normalize_text(ocr_text)
    document_type = "hematologie" if ("hematologie" in ntext or any("hem" in _normalize_text(t.name) for t in tests)) else "unknown"

    return StructuredExtraction(
        document_type=document_type,
        tests=tests,
        global_needs_review=global_review,
        warnings=warnings,
    )
