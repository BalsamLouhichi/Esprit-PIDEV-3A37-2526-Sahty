from pydantic import BaseModel, Field


class Anomaly(BaseModel):
    name: str = ""
    value: str = ""
    reference: str = ""
    status: str = "UNKNOWN"
    severity: str = "LOW"
    note: str = ""


class AnalysisResult(BaseModel):
    summary: str = ""
    danger_level: str = "LOW"
    danger_score: int = 0
    anomalies: list[Anomaly] = Field(default_factory=list)
    recommendations: list[str] = Field(default_factory=list)
    model: str = ""
    raw_model_output: str = ""


class LLMInterpretation(BaseModel):
    clinician_summary: str = ""
    patient_summary: str = ""
    urgency: str = "ROUTINE"
    urgency_reason: str = ""
    suggested_actions: list[str] = Field(default_factory=list)
    red_flags: list[str] = Field(default_factory=list)
    confidence: float = 0.0
    model: str = ""
    raw_model_output: str = ""


class ExtractedTest(BaseModel):
    name: str
    value_raw: str | None = None
    value_num: float | None = None
    unit: str | None = None
    reference_text: str | None = None
    ref_low: float | None = None
    ref_high: float | None = None
    flag: str | None = None
    status: str = "UNKNOWN"
    needs_review: bool = True
    confidence: float = 0.3
    notes: list[str] = Field(default_factory=list)


class StructuredExtraction(BaseModel):
    document_type: str = "hematologie"
    tests: list[ExtractedTest] = Field(default_factory=list)
    global_needs_review: bool = True
    warnings: list[str] = Field(default_factory=list)


class OCRResult(BaseModel):
    engine: str
    pages: int = 1
    text: str


class AnalyzeResponse(BaseModel):
    filename: str
    ocr: OCRResult
    analysis: AnalysisResult
    structured: StructuredExtraction | None = None
    llm_interpretation: LLMInterpretation | None = None
    metric_glossary: dict[str, str] = Field(default_factory=dict)


class MetricGlossaryRequest(BaseModel):
    metric_names: list[str] = Field(default_factory=list)
    model: str | None = None


class MetricGlossaryResponse(BaseModel):
    metric_glossary: dict[str, str] = Field(default_factory=dict)

