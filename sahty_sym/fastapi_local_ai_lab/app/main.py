import os

from fastapi import FastAPI, File, Form, HTTPException, Request, UploadFile
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from .analysis_builder import build_analysis_from_structured
from .domain_context_service import DomainContextService
from .layout_row_parser import LayoutParserError, parse_document_with_layout
from .ocr_service import OCRExtraction, OCRServiceError, extract_text
from .ollama_service import (
    OllamaServiceError,
    describe_metric_names_with_ollama,
    describe_metrics_with_ollama,
    interpret_structured_with_ollama,
)
from .rule_based_extractor import extract_hematology_structured
from .schemas import AnalyzeResponse, LLMInterpretation, MetricGlossaryRequest, MetricGlossaryResponse


BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMPLATES_DIR = os.path.join(BASE_DIR, "templates")
STATIC_DIR = os.path.join(BASE_DIR, "static")
URGENT_RECOMMENDATION = (
    "Si vous avez des symptomes importants (douleur thoracique, essoufflement, malaise, jaunisse, confusion), contactez les urgences."
)

app = FastAPI(
    title="FastAPI Local AI Lab",
    description="Local OCR + local Ollama analysis for blood reports.",
    version="1.0.0",
)
app.mount("/static", StaticFiles(directory=STATIC_DIR), name="static")
templates = Jinja2Templates(directory=TEMPLATES_DIR)
domain_context_service = DomainContextService()
domain_context_service.auto_load_from_env()


def _is_true(value: str | None) -> bool:
    if not value:
        return False
    return value.strip().lower() in {"1", "true", "yes", "on"}


def _is_llm_urgent(llm_interpretation: LLMInterpretation | None) -> bool:
    if llm_interpretation is None:
        return False
    return (llm_interpretation.urgency or "").strip().upper() == "URGENT"


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.get("/favicon.ico", include_in_schema=False)
def favicon() -> RedirectResponse:
    return RedirectResponse(url="/static/favicon.svg", status_code=307)


@app.get("/api/domain-context")
def domain_context_status() -> dict:
    return domain_context_service.get_status_payload()


@app.post("/api/domain-context/load")
def domain_context_load(dataset_path: str = Form(...), max_tests: int = Form(default=50)) -> dict:
    meta = domain_context_service.load_from_path(dataset_path, max_tests=max_tests)
    return domain_context_service.get_status_payload() | {"loaded_now": meta.loaded}


@app.get("/", response_class=HTMLResponse)
def home(request: Request) -> HTMLResponse:
    return templates.TemplateResponse(
        request=request,
        name="index.html",
        context={
            "error": None,
            "result": None,
            "default_model": os.getenv("OLLAMA_MODEL", "llama3:latest"),
            "default_engine": os.getenv("OCR_ENGINE", "tesseract"),
            "default_lang": os.getenv("OCR_LANG", "fra+eng"),
            "default_use_ollama": os.getenv("USE_OLLAMA_ENRICH", "false"),
            "domain_status": domain_context_service.get_status_payload(),
        },
    )


@app.post("/api/analyze", response_model=AnalyzeResponse)
async def api_analyze(
    file: UploadFile = File(...),
    ocr_engine: str = Form(default=os.getenv("OCR_ENGINE", "tesseract")),
    lang: str = Form(default=os.getenv("OCR_LANG", "fra+eng")),
    model: str = Form(default=os.getenv("OLLAMA_MODEL", "llama3:latest")),
    use_ollama: str = Form(default=os.getenv("USE_OLLAMA_ENRICH", "false")),
    ollama_mode: str = Form(default=os.getenv("OLLAMA_MODE", "glossary_only")),
) -> AnalyzeResponse:
    content = await file.read()
    if not content:
        raise HTTPException(status_code=400, detail="Empty file.")
    if len(content) > 20 * 1024 * 1024:
        raise HTTPException(status_code=400, detail="File too large (max 20MB).")

    try:
        llm_interpretation: LLMInterpretation | None = None
        metric_glossary: dict[str, str] = {}
        try:
            parsed = parse_document_with_layout(
                content,
                file.filename or "upload",
                ocr_engine=ocr_engine,
                lang=lang,
            )
            ocr = OCRExtraction(text=parsed.ocr_text, pages=parsed.pages, engine=parsed.engine)
            structured = parsed.structured
        except LayoutParserError as layout_exc:
            # Safe fallback: legacy OCR + text parser if layout parser fails.
            ocr = extract_text(content, file.filename or "upload", engine=ocr_engine, lang=lang)
            structured = extract_hematology_structured(ocr.text)
            structured.warnings.append(f"Layout parser fallback used: {layout_exc}")

        analysis = build_analysis_from_structured(structured)

        if _is_true(use_ollama):
            domain_context = domain_context_service.get_context_text()
            mode = (ollama_mode or "").strip().lower()
            run_interpretation = mode in {"full", "all", "interpretation"}
            run_glossary = mode in {"full", "all", "glossary", "glossary_only", "metric_glossary", ""}

            if run_interpretation:
                try:
                    llm_interpretation = interpret_structured_with_ollama(
                        structured=structured,
                        model=model,
                        domain_context=domain_context,
                    )
                    if _is_llm_urgent(llm_interpretation) and URGENT_RECOMMENDATION not in analysis.recommendations:
                        analysis.recommendations.append(URGENT_RECOMMENDATION)
                    if llm_interpretation.suggested_actions:
                        merged = list(
                            dict.fromkeys(analysis.recommendations + llm_interpretation.suggested_actions)
                        )
                        analysis.recommendations = merged
                    analysis.model = f"{analysis.model}+{llm_interpretation.model}"
                except OllamaServiceError as exc:
                    # Do not fail analysis if Ollama enrichment is unavailable.
                    structured.warnings.append(f"Ollama interpretation unavailable: {exc}")

            if run_glossary:
                try:
                    metric_glossary = describe_metrics_with_ollama(
                        structured=structured,
                        model=model,
                        domain_context=domain_context,
                    )
                except OllamaServiceError as exc:
                    structured.warnings.append(f"Ollama metric glossary unavailable: {exc}")
    except OCRServiceError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    return AnalyzeResponse(
        filename=file.filename or "upload",
        ocr={"engine": ocr.engine, "pages": ocr.pages, "text": ocr.text},
        analysis=analysis,
        structured=structured,
        llm_interpretation=llm_interpretation,
        metric_glossary=metric_glossary,
    )


@app.post("/api/metric-glossary", response_model=MetricGlossaryResponse)
def api_metric_glossary(payload: MetricGlossaryRequest) -> MetricGlossaryResponse:
    metric_names = [str(x).strip() for x in payload.metric_names if str(x).strip()]
    if not metric_names:
        return MetricGlossaryResponse(metric_glossary={})

    try:
        glossary = describe_metric_names_with_ollama(
            metric_names=metric_names,
            model=payload.model or os.getenv("OLLAMA_MODEL", "llama3:latest"),
            domain_context=domain_context_service.get_context_text(),
        )
    except OllamaServiceError as exc:
        raise HTTPException(status_code=503, detail=f"Ollama metric glossary unavailable: {exc}") from exc

    return MetricGlossaryResponse(metric_glossary=glossary)


@app.post("/analyze", response_class=HTMLResponse)
async def analyze_form(
    request: Request,
    file: UploadFile = File(...),
    ocr_engine: str = Form(default=os.getenv("OCR_ENGINE", "tesseract")),
    lang: str = Form(default=os.getenv("OCR_LANG", "fra+eng")),
    model: str = Form(default=os.getenv("OLLAMA_MODEL", "llama3:latest")),
    use_ollama: str = Form(default=os.getenv("USE_OLLAMA_ENRICH", "false")),
    ollama_mode: str = Form(default=os.getenv("OLLAMA_MODE", "glossary_only")),
) -> HTMLResponse:
    try:
        result = await api_analyze(
            file=file,
            ocr_engine=ocr_engine,
            lang=lang,
            model=model,
            use_ollama=use_ollama,
            ollama_mode=ollama_mode,
        )
        error = None
    except HTTPException as exc:
        result = None
        error = str(exc.detail)

    return templates.TemplateResponse(
        request=request,
        name="index.html",
        context={
            "error": error,
            "result": result,
            "default_model": model,
            "default_engine": ocr_engine,
            "default_lang": lang,
            "default_use_ollama": use_ollama,
            "domain_status": domain_context_service.get_status_payload(),
        },
    )




