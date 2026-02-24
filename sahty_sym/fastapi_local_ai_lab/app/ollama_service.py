import json
import os
import re
import unicodedata

import requests

from .schemas import AnalysisResult, Anomaly, LLMInterpretation, StructuredExtraction


class OllamaServiceError(Exception):
    pass


def _env_int(name: str, default: int) -> int:
    try:
        return int(os.getenv(name, str(default)))
    except ValueError:
        return default


def _env_float(name: str, default: float) -> float:
    try:
        return float(os.getenv(name, str(default)))
    except ValueError:
        return default


def _ollama_options() -> dict:
    # Keep generation short for web request latency.
    return {
        "temperature": _env_float("OLLAMA_TEMPERATURE", 0.1),
        "num_predict": _env_int("OLLAMA_NUM_PREDICT", 220),
        "num_ctx": _env_int("OLLAMA_NUM_CTX", 4096),
    }


def _extract_json(raw: str) -> dict:
    cleaned = raw.strip()
    if cleaned.startswith("```"):
        cleaned = re.sub(r"^```[a-zA-Z]*\s*", "", cleaned)
        cleaned = re.sub(r"\s*```$", "", cleaned)

    try:
        return json.loads(cleaned)
    except json.JSONDecodeError:
        pass

    match = re.search(r"\{[\s\S]*\}", cleaned)
    if match:
        return json.loads(match.group(0))

    raise OllamaServiceError("Model output is not valid JSON.")


def _normalize(data: dict, model: str, raw: str) -> AnalysisResult:
    anomalies_payload = data.get("anomalies") or []
    anomalies: list[Anomaly] = []
    for item in anomalies_payload:
        if not isinstance(item, dict):
            continue
        anomalies.append(
            Anomaly(
                name=str(item.get("name", "")),
                value=str(item.get("value", "")),
                reference=str(item.get("reference", "")),
                status=str(item.get("status", "UNKNOWN")).upper(),
                severity=str(item.get("severity", "LOW")).upper(),
                note=str(item.get("note", "")),
            )
        )

    recs = data.get("recommendations") or []
    recommendations = [str(x) for x in recs if isinstance(x, (str, int, float))]

    score = data.get("danger_score", 0)
    try:
        score = int(score)
    except (TypeError, ValueError):
        score = 0
    score = max(0, min(100, score))

    level = str(data.get("danger_level", "LOW")).upper()
    if level not in {"LOW", "MEDIUM", "HIGH"}:
        level = "LOW"

    return AnalysisResult(
        summary=str(data.get("summary", "")),
        danger_level=level,
        danger_score=score,
        anomalies=anomalies,
        recommendations=recommendations,
        model=model,
        raw_model_output=raw,
    )


def _normalize_interpretation(data: dict, model: str, raw: str) -> LLMInterpretation:
    urgency_raw = str(data.get("urgency", "ROUTINE")).strip().upper()
    urgency_map = {
        "ROUTINE": "ROUTINE",
        "NORMAL": "ROUTINE",
        "LOW": "ROUTINE",
        "PRIORITY_24H": "PRIORITY_24H",
        "PRIORITAIRE_24H": "PRIORITY_24H",
        "PRIORITAIRE": "PRIORITY_24H",
        "MEDIUM": "PRIORITY_24H",
        "URGENT": "URGENT",
        "HIGH": "URGENT",
        "CRITIQUE": "URGENT",
        "CRITICAL": "URGENT",
    }
    urgency = urgency_map.get(urgency_raw, "ROUTINE")

    actions_payload = data.get("suggested_actions") or []
    suggested_actions = [str(x).strip() for x in actions_payload if str(x).strip()]

    red_flags_payload = data.get("red_flags") or []
    red_flags = [str(x).strip() for x in red_flags_payload if str(x).strip()]

    confidence = data.get("confidence", 0.0)
    try:
        confidence = float(confidence)
    except (TypeError, ValueError):
        confidence = 0.0
    confidence = max(0.0, min(1.0, confidence))

    return LLMInterpretation(
        clinician_summary=str(data.get("clinician_summary", "")).strip(),
        patient_summary=str(data.get("patient_summary", "")).strip(),
        urgency=urgency,
        urgency_reason=str(data.get("urgency_reason", "")).strip(),
        suggested_actions=suggested_actions,
        red_flags=red_flags,
        confidence=confidence,
        model=model,
        raw_model_output=raw,
    )


def _normalize_name_key(text: str) -> str:
    normalized = unicodedata.normalize("NFD", text or "")
    normalized = "".join(ch for ch in normalized if unicodedata.category(ch) != "Mn")
    return re.sub(r"[^a-z0-9]+", "", normalized.lower())


def _normalize_metric_glossary(data: dict, test_names: list[str]) -> dict[str, str]:
    if not test_names:
        return {}

    key_to_name: dict[str, str] = {}
    for name in test_names:
        cleaned = (name or "").strip()
        if not cleaned:
            continue
        key = _normalize_name_key(cleaned)
        if key and key not in key_to_name:
            key_to_name[key] = cleaned

    payload = data.get("metric_glossary")
    if payload is None:
        payload = data.get("glossary")

    entries: list[tuple[str, str]] = []
    if isinstance(payload, dict):
        entries = [(str(k), str(v)) for k, v in payload.items()]
    elif isinstance(payload, list):
        for item in payload:
            if not isinstance(item, dict):
                continue
            entries.append((str(item.get("name", "")), str(item.get("description", ""))))

    glossary: dict[str, str] = {}
    for raw_name, raw_description in entries:
        key = _normalize_name_key(raw_name)
        target_name = key_to_name.get(key)
        if not target_name:
            continue

        description = _sanitize_glossary_description(raw_description or "")
        if not description:
            continue
        if len(description) > 240:
            description = f"{description[:237].rstrip()}..."

        glossary[target_name] = description

    return _postprocess_glossary(glossary)


def _extract_metric_glossary_from_text(raw: str, test_names: list[str]) -> dict[str, str]:
    if not raw.strip() or not test_names:
        return {}

    key_to_name: dict[str, str] = {}
    for name in test_names:
        cleaned = (name or "").strip()
        if not cleaned:
            continue
        key = _normalize_name_key(cleaned)
        if key and key not in key_to_name:
            key_to_name[key] = cleaned

    def resolve_name(candidate: str) -> str:
        key = _normalize_name_key(candidate)
        if not key:
            return ""
        return key_to_name.get(key, "")

    glossary: dict[str, str] = {}
    lines = [re.sub(r"\s+", " ", line).strip() for line in raw.splitlines()]
    for line in lines:
        if not line:
            continue
        line = re.sub(r"^[\-\*\u2022\d\.\)\(]+\s*", "", line).strip()
        if not line:
            continue

        parts: tuple[str, str] | None = None
        for sep in [":", " - ", " — ", " – ", " => "]:
            if sep in line:
                left, right = line.split(sep, 1)
                parts = (left.strip(), right.strip())
                break
        if not parts:
            continue

        candidate, description = parts
        candidate = re.sub(r"[*`_]", "", candidate).strip()
        description = _sanitize_glossary_description(description)
        if not candidate or not description:
            continue

        metric_name = resolve_name(candidate)
        if not metric_name:
            continue

        if len(description) > 240:
            description = f"{description[:237].rstrip()}..."

        if metric_name not in glossary:
            glossary[metric_name] = description

    return _postprocess_glossary(glossary)


def _sanitize_glossary_description(value: str) -> str:
    text = re.sub(r"\s+", " ", str(value or "")).strip()
    if not text:
        return ""

    text = re.sub(r'^[\"\'`]+', "", text)
    text = re.sub(r'[\"\'`,;:]+$', "", text)
    text = text.strip()
    return text


def _postprocess_glossary(glossary: dict[str, str]) -> dict[str, str]:
    if not glossary:
        return {}

    normalized_counts: dict[str, int] = {}
    for description in glossary.values():
        key = _normalize_name_key(description)
        if key:
            normalized_counts[key] = normalized_counts.get(key, 0) + 1

    result: dict[str, str] = {}
    for metric, description in glossary.items():
        desc = _sanitize_glossary_description(description)
        if not desc:
            continue

        key = _normalize_name_key(desc)
        if key and normalized_counts.get(key, 0) > 2:
            # Drop repeated generic outputs; better to return empty and let caller retry/fallback.
            continue

        result[metric] = desc

    return result


def fallback_metric_glossary(metric_names: list[str]) -> dict[str, str]:
    if not metric_names:
        return {}

    descriptions_by_key: dict[str, str] = {
        _normalize_name_key("CRP"): "Marqueur inflammatoire: son augmentation suggere un processus inflammatoire en cours.",
        _normalize_name_key("Hematies"): "Mesure la quantite de globules rouges qui transportent l oxygene dans le sang.",
        _normalize_name_key("Hemoglobine"): "Proteine des globules rouges qui transporte l oxygene; elle aide a evaluer l anemie.",
        _normalize_name_key("Hematocrite"): "Pourcentage du volume sanguin occupe par les globules rouges.",
        _normalize_name_key("VGM"): "Indique la taille moyenne des globules rouges.",
        _normalize_name_key("TCMH"): "Quantite moyenne d hemoglobine contenue dans chaque globule rouge.",
        _normalize_name_key("CCMH"): "Concentration moyenne d hemoglobine dans les globules rouges.",
        _normalize_name_key("Leucocytes"): "Nombre de globules blancs, utilises pour evaluer la reponse immunitaire et infectieuse.",
        _normalize_name_key("Polynucleaires neutrophiles"): "Sous-type de globules blancs, souvent eleve en cas d infection bacterienne.",
        _normalize_name_key("Polynucleaires eosinophiles"): "Sous-type de globules blancs, souvent lie aux allergies ou aux parasitoses.",
        _normalize_name_key("Polynucleaires basophiles"): "Sous-type de globules blancs implique dans certaines reactions inflammatoires/allergiques.",
        _normalize_name_key("Lymphocytes"): "Sous-type de globules blancs implique dans la defense immunitaire.",
        _normalize_name_key("Monocytes"): "Sous-type de globules blancs participant a la reponse inflammatoire et immunitaire.",
        _normalize_name_key("Numeration des plaquettes"): "Nombre de plaquettes, important pour l hemostase et la coagulation.",
        _normalize_name_key("ALAT"): "Enzyme hepatique: une hausse peut refleter une atteinte des cellules du foie.",
        _normalize_name_key("ASAT"): "Enzyme presente surtout dans le foie et le muscle; une hausse oriente vers une souffrance tissulaire.",
        _normalize_name_key("Creatinine"): "Marqueur de la fonction renale, interprete avec l age, le sexe et le contexte clinique.",
        _normalize_name_key("Glycemie a jeun"): "Mesure du glucose a jeun, utile pour le depistage et le suivi des troubles glycemiques.",
        _normalize_name_key("HbA1c"): "Reflete la moyenne de la glycemie sur environ 2 a 3 mois.",
        _normalize_name_key("Vitesse de sedimentation 1ere heure"): "Marqueur non specifique d inflammation, a interpreter avec les autres donnees.",
    }

    aliases: dict[str, str] = {
        _normalize_name_key("alt"): _normalize_name_key("ALAT"),
        _normalize_name_key("ast"): _normalize_name_key("ASAT"),
        _normalize_name_key("wbc"): _normalize_name_key("Leucocytes"),
        _normalize_name_key("hb"): _normalize_name_key("Hemoglobine"),
        _normalize_name_key("glycemie"): _normalize_name_key("Glycemie a jeun"),
        _normalize_name_key("plaquettes"): _normalize_name_key("Numeration des plaquettes"),
        _normalize_name_key("plt"): _normalize_name_key("Numeration des plaquettes"),
        _normalize_name_key("vs"): _normalize_name_key("Vitesse de sedimentation 1ere heure"),
    }

    glossary: dict[str, str] = {}
    for raw_name in metric_names:
        name = (raw_name or "").strip()
        if not name:
            continue

        key = _normalize_name_key(name)
        if not key:
            continue

        canonical_key = aliases.get(key, key)
        description = descriptions_by_key.get(canonical_key)
        if description:
            glossary[name] = description

    return glossary


def interpret_structured_with_ollama(
    structured: StructuredExtraction,
    model: str | None = None,
    domain_context: str = "",
) -> LLMInterpretation:
    if not structured.tests:
        raise OllamaServiceError("No structured tests to interpret.")

    ollama_base = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
    selected_model = model or os.getenv("OLLAMA_MODEL", "llama3:latest")
    timeout_sec = _env_int("OLLAMA_TIMEOUT_SECONDS", 120)

    domain_block = ""
    if domain_context.strip():
        domain_block = f"\n\nDOMAIN_CONTEXT_START\n{domain_context}\nDOMAIN_CONTEXT_END\n"

    prompt = f"""
You are a medical lab interpretation assistant.
You receive structured extraction that is the source of truth.
Return ONLY valid JSON (no markdown, no prose around JSON).

Rules:
- NEVER change numeric values from extraction.
- NEVER invent missing values or references.
- If a test has needs_review=true, explicitly mention uncertainty.
- Provide conservative safety-focused interpretation.

Required JSON shape:
{{
  "clinician_summary": "short technical interpretation for clinician",
  "patient_summary": "simple explanation for patient in plain language",
  "urgency": "ROUTINE|PRIORITY_24H|URGENT",
  "urgency_reason": "why this urgency level",
  "suggested_actions": ["action 1", "action 2"],
  "red_flags": ["flag 1", "flag 2"],
  "confidence": 0.0
}}

STRUCTURED_EXTRACTION_START
{structured.model_dump_json()}
STRUCTURED_EXTRACTION_END
{domain_block}
""".strip()

    payload = {
        "model": selected_model,
        "prompt": prompt,
        "stream": False,
        "format": "json",
        "options": _ollama_options(),
    }

    try:
        response = requests.post(
            f"{ollama_base}/api/generate",
            json=payload,
            timeout=timeout_sec,
        )
        response.raise_for_status()
        body = response.json()
        raw_output = str(body.get("response", "")).strip()
    except requests.RequestException as exc:
        raise OllamaServiceError(
            "Could not reach Ollama. Make sure `ollama serve` is running."
        ) from exc

    parsed = _extract_json(raw_output)
    return _normalize_interpretation(parsed, selected_model, raw_output)


def describe_metrics_with_ollama(
    structured: StructuredExtraction,
    model: str | None = None,
    domain_context: str = "",
) -> dict[str, str]:
    test_names: list[str] = []
    seen: set[str] = set()
    for test in structured.tests:
        name = (test.name or "").strip()
        if not name or name in seen:
            continue
        seen.add(name)
        test_names.append(name)

    return describe_metric_names_with_ollama(
        metric_names=test_names,
        model=model,
        domain_context=domain_context,
    )


def describe_metric_names_with_ollama(
    metric_names: list[str],
    model: str | None = None,
    domain_context: str = "",
) -> dict[str, str]:
    test_names: list[str] = []
    seen: set[str] = set()
    for name in metric_names:
        cleaned = (name or "").strip()
        if not cleaned or cleaned in seen:
            continue
        seen.add(cleaned)
        test_names.append(cleaned)

    if not test_names:
        return {}

    ollama_base = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
    selected_model = model or os.getenv("OLLAMA_MODEL", "llama3:latest")
    timeout_sec = _env_int("OLLAMA_TIMEOUT_SECONDS", 120)

    domain_block = ""
    if domain_context.strip():
        domain_block = f"\n\nDOMAIN_CONTEXT_START\n{domain_context}\nDOMAIN_CONTEXT_END\n"

    prompt = f"""
You are a medical lab glossary assistant.
Return ONLY valid JSON (no markdown, no prose around JSON).

Rules:
- Use ONLY the provided test names.
- Return exactly one short French sentence per test (max 180 chars).
- The sentence must define what the marker generally reflects.
- No diagnosis, no treatment advice.
- Do not wrap strings with extra quotes. Do not add trailing commas.

Required JSON shape:
{{
  "metric_glossary": {{
    "EXACT_TEST_NAME_FROM_INPUT": "short description"
  }}
}}

TEST_NAMES_START
{json.dumps(test_names, ensure_ascii=False)}
TEST_NAMES_END
{domain_block}
""".strip()

    options = _ollama_options()
    options["num_predict"] = _env_int("OLLAMA_GLOSSARY_NUM_PREDICT", 260)

    payload = {
        "model": selected_model,
        "prompt": prompt,
        "stream": False,
        "format": "json",
        "options": options,
    }

    try:
        response = requests.post(
            f"{ollama_base}/api/generate",
            json=payload,
            timeout=timeout_sec,
        )
        response.raise_for_status()
        body = response.json()
        raw_output = str(body.get("response", "")).strip()
    except requests.RequestException as exc:
        fallback = fallback_metric_glossary(test_names)
        if fallback:
            return fallback
        raise OllamaServiceError(
            "Could not reach Ollama. Make sure `ollama serve` is running."
        ) from exc

    try:
        parsed = _extract_json(raw_output)
        glossary = _normalize_metric_glossary(parsed, test_names)
    except OllamaServiceError:
        glossary = {}

    if not glossary:
        glossary = _extract_metric_glossary_from_text(raw_output, test_names)

    if glossary:
        # Ensure missing metrics still get a deterministic local definition.
        fallback = fallback_metric_glossary(test_names)
        for metric_name, description in fallback.items():
            glossary.setdefault(metric_name, description)
        return glossary

    fallback = fallback_metric_glossary(test_names)
    if fallback:
        return fallback

    if not glossary:
        raise OllamaServiceError("Model output is not valid JSON.")

    return glossary


def analyze_with_ollama(
    report_text: str,
    model: str | None = None,
    structured: StructuredExtraction | None = None,
    domain_context: str = "",
) -> AnalysisResult:
    if not report_text.strip():
        raise OllamaServiceError("No OCR text to analyze.")

    ollama_base = os.getenv("OLLAMA_BASE_URL", "http://127.0.0.1:11434").rstrip("/")
    selected_model = model or os.getenv("OLLAMA_MODEL", "llama3:latest")
    timeout_sec = _env_int("OLLAMA_TIMEOUT_SECONDS", 120)

    structured_block = ""
    if structured is not None:
        structured_block = f"\n\nRULE_BASED_EXTRACTION_START\n{structured.model_dump_json()}\nRULE_BASED_EXTRACTION_END\n"

    domain_block = ""
    if domain_context.strip():
        domain_block = f"\n\nDOMAIN_CONTEXT_START\n{domain_context}\nDOMAIN_CONTEXT_END\n"

    prompt = f"""
You are a medical lab report assistant.
Analyze the blood report text and return ONLY strict JSON (no markdown, no prose).
Do not invent values. If uncertain, keep conservative output.

Required JSON shape:
{{
  "summary": "short clinical summary",
  "danger_level": "LOW|MEDIUM|HIGH",
  "danger_score": 0,
  "anomalies": [
    {{
      "name": "test name",
      "value": "observed value",
      "reference": "normal range",
      "status": "LOW|HIGH|NORMAL|UNKNOWN",
      "severity": "LOW|MEDIUM|HIGH",
      "note": "short explanation"
    }}
  ],
  "recommendations": ["item 1", "item 2"]
}}

Rules:
- Keep danger_score between 0 and 100.
- If information is missing, use conservative values.
- Use the report text only.
- If RULE_BASED_EXTRACTION is provided, respect it as source of truth for tests/status.

REPORT_TEXT_START
{report_text}
REPORT_TEXT_END
{structured_block}
{domain_block}
""".strip()

    payload = {
        "model": selected_model,
        "prompt": prompt,
        "stream": False,
        "format": "json",
        "options": _ollama_options(),
    }

    try:
        response = requests.post(
            f"{ollama_base}/api/generate",
            json=payload,
            timeout=timeout_sec,
        )
        response.raise_for_status()
        body = response.json()
        raw_output = str(body.get("response", "")).strip()
    except requests.RequestException as exc:
        raise OllamaServiceError(
            "Could not reach Ollama. Make sure `ollama serve` is running."
        ) from exc

    parsed = _extract_json(raw_output)
    return _normalize(parsed, selected_model, raw_output)



