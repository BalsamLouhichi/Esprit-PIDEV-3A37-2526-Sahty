from .schemas import AnalysisResult, Anomaly, StructuredExtraction


def _severity_from_status(status: str) -> str:
    if status == "HIGH":
        return "HIGH"
    if status == "LOW":
        return "MEDIUM"
    return "LOW"


def _danger_score(structured: StructuredExtraction) -> int:
    score = 0
    for t in structured.tests:
        if t.status == "HIGH":
            score += 20
        elif t.status == "LOW":
            score += 15
        elif t.status == "UNKNOWN" and t.needs_review:
            score += 5
    return max(0, min(100, score))


def _danger_level(score: int) -> str:
    if score >= 70:
        return "HIGH"
    if score >= 35:
        return "MEDIUM"
    return "LOW"


def _is_transaminase_marker(name: str) -> bool:
    token = (name or "").strip().upper()
    if not token:
        return False
    return any(k in token for k in ("ASAT", "AST", "ALAT", "ALT", "TRANSAMINASE"))


def _format_rule_based_anomaly_description(anomaly: Anomaly) -> str:
    parts: list[str] = []
    status = (anomaly.status or "").strip().upper()
    severity = (anomaly.severity or "").strip().upper()
    note = (anomaly.note or "").strip()

    if status:
        parts.append(f"Statut: {status}")
    if severity:
        parts.append(f"Severite: {severity}")
    if note:
        parts.append(f"Note: {note}")

    if not parts:
        return "Description rule-based indisponible pour ce parametre."
    return " | ".join(parts)


def _has_urgent_criterion(structured: StructuredExtraction, score: int) -> bool:
    # Very high global score is considered urgent.
    if score >= 85:
        return True

    for t in structured.tests:
        status = (t.status or "").upper()
        flag = (t.flag or "").upper()
        if any(k in flag for k in ("CRITIQUE", "CRITICAL", "URGENT", "ALERTE")):
            return True

        if status != "HIGH":
            continue

        if (
            _is_transaminase_marker(t.name)
            and t.value_num is not None
            and t.ref_high is not None
            and t.ref_high > 0
            and t.value_num >= (5 * t.ref_high)
        ):
            return True

    return False


def build_analysis_from_structured(structured: StructuredExtraction) -> AnalysisResult:
    anomalies: list[Anomaly] = []
    high_count = low_count = unknown_count = 0

    for t in structured.tests:
        if t.status == "HIGH":
            high_count += 1
        elif t.status == "LOW":
            low_count += 1
        elif t.status == "UNKNOWN":
            unknown_count += 1

        if t.status != "NORMAL" or t.needs_review:
            notes = "; ".join(t.notes) if t.notes else ""
            anomalies.append(
                Anomaly(
                    name=t.name,
                    value=t.value_raw or "",
                    reference=t.reference_text or "",
                    status=t.status,
                    severity=_severity_from_status(t.status),
                    note=notes,
                )
            )

    score = _danger_score(structured)
    level = _danger_level(score)

    summary = (
        f"Extraction rule-based: {len(structured.tests)} tests, "
        f"{high_count} HIGH, {low_count} LOW, {unknown_count} UNKNOWN."
    )
    if structured.global_needs_review:
        summary += " Human validation recommended for uncertain mappings."

    recommendations: list[str] = []
    if high_count > 0 or low_count > 0 or structured.global_needs_review:
        recommendations.append(
            "Discutez de ces valeurs avec un professionnel de sante (symptomes, antecedents, traitements)."
        )
    if _has_urgent_criterion(structured, score):
        recommendations.append(
            "Si vous avez des symptomes importants (douleur thoracique, essoufflement, malaise, jaunisse, confusion), contactez les urgences."
        )

    return AnalysisResult(
        summary=summary,
        danger_level=level,
        danger_score=score,
        anomalies=anomalies,
        recommendations=recommendations,
        model="rule-based-v2",
        raw_model_output="",
    )


def build_rule_based_metric_glossary(analysis: AnalysisResult) -> dict[str, str]:
    glossary: dict[str, str] = {}
    for anomaly in analysis.anomalies:
        name = (anomaly.name or "").strip()
        if not name or name in glossary:
            continue
        glossary[name] = _format_rule_based_anomaly_description(anomaly)
    return glossary
