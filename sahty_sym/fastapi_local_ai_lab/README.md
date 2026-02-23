# FastAPI Local AI Lab (OCR + Ollama)

Standalone local project for blood report testing:

- OCR local: `Tesseract` (default) or `PaddleOCR` (optional)
- LLM local: `Ollama` (`mistral`, `llama3.1`, etc.)
- Web test UI + JSON API
- Layout-aware parsing (coordinates): PDF native words or OCR boxes

No `ZAI_API_KEY`, no `Gemini` key.

## 1) Project path

`C:\Users\balsa\Desktop\piiiiiii\sahty3\fastapi_local_ai_lab`

## 2) Prerequisites

- Python 3.10+
- Ollama installed
- Tesseract installed and available in PATH

### Install Ollama model

```powershell
ollama pull llama3:latest
```

or:

```powershell
ollama pull mistral
```

## 3) Run quickly (Windows PowerShell)

```powershell
cd C:\Users\balsa\Desktop\piiiiiii\sahty3\fastapi_local_ai_lab
Set-ExecutionPolicy -Scope Process Bypass
.\start.ps1
```

Open: `http://127.0.0.1:8090`

## 4) Manual run

```powershell
cd C:\Users\balsa\Desktop\piiiiiii\sahty3\fastapi_local_ai_lab
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8090 --reload
```

## 5) Optional PaddleOCR

```powershell
pip install -r requirements-paddle.txt
```

Then in UI, choose OCR engine = `paddle`.

## 6) API usage

Endpoint:

- `POST /api/analyze` (multipart/form-data)

Fields:

- `file`: PDF/image
- `ocr_engine`: `tesseract|paddle|auto` (`auto` recommended for scanned PDFs)
- `lang`: OCR language (default `fra+eng`)
- `model`: Ollama model name

Example with curl:

```bash
curl -X POST "http://127.0.0.1:8090/api/analyze" \
  -F "file=@C:/Users/balsa/Downloads/bilan_test_textuel.pdf" \
  -F "ocr_engine=tesseract" \
  -F "lang=fra+eng" \
  -F "model=llama3:latest"
```

### Structured extraction JSON

`/api/analyze` now returns:

- `analysis`: score/summary/anomalies (rule-based, deterministic)
- `structured`: strict test extraction with `value_raw`, `reference_text`, `flag`, `status`, `needs_review`, `confidence`
- `llm_interpretation` (optional): post-extraction interpretation from Ollama
  - `clinician_summary`, `patient_summary`, `urgency`, `urgency_reason`, `suggested_actions`, `red_flags`, `confidence`

### Parsing pipeline (anti-column-mix)

1. `detect_pdf_textual()`:
   - If text PDF -> parse native words with coordinates (no OCR).
2. Else `ocr_with_boxes()`:
   - Tesseract/PaddleOCR word boxes `(x,y,w,h)`.
3. `group_by_lines()`:
   - Group tokens by visual `y` tolerance.
4. `split_into_columns()`:
   - Use x-based columns: name/value/unit/reference/flag.
5. `build_rows()`:
   - Build strict rows (`null + needs_review=true` when uncertain).
6. `validate_rows()`:
   - Detect obvious mismatch and detach incoherent reference.
7. Apply deterministic business rules for `HIGH/LOW/NORMAL/UNKNOWN`.
8. LLM is optional enrichment only (summary/synonyms), not column mapping.

### Load Kaggle dataset context (optional)

1. Download CSV locally from Kaggle.
2. Load it into the app:

```bash
curl -X POST "http://127.0.0.1:8090/api/domain-context/load" \
  -F "dataset_path=C:/path/to/laboratory_test_results.csv" \
  -F "max_tests=50"
```

3. Check status:

```bash
curl "http://127.0.0.1:8090/api/domain-context"
```

Or set automatic loading via env:

```env
DOMAIN_DATASET_PATH=C:/path/to/laboratory_test_results.csv
DOMAIN_MAX_TESTS=50
USE_OLLAMA_ENRICH=true
```

## 7) Notes

- The app is for technical testing only.
- Medical interpretation must be validated by a healthcare professional.

## 8) Unit tests

Run:

```powershell
cd C:\Users\balsa\Desktop\piiiiiii\sahty3\fastapi_local_ai_lab
python -m unittest tests\test_layout_parser.py -v
```

Optional explicit file paths:

```powershell
$env:BILAN_TEXT_PDF_PATH="C:\Users\balsa\Downloads\bilan_test_textuel.pdf"
$env:BILAN_SCAN_PDF_PATH="C:\Users\balsa\Downloads\bilan2.pdf"
python -m unittest tests\test_layout_parser.py -v
```
