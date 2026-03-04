param(
    [int]$Port = 8090,
    [string]$Model = "qwen2.5:0.5b",
    [string]$OcrEngine = "tesseract",
    [switch]$SkipInstall
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $root

if (-not (Test-Path ".venv\\Scripts\\python.exe")) {
    python -m venv .venv
}

. .\.venv\Scripts\Activate.ps1

if (-not $SkipInstall) {
    pip install --upgrade pip
    pip install -r requirements.txt
}

$env:OLLAMA_MODEL = $Model
$env:OCR_ENGINE = $OcrEngine
$env:OCR_PDF_SCALE = "2.0"
$env:OCR_TESSERACT_FAST = "true"
$env:OCR_AUTO_TESSERACT_MIN_SCORE = "0.50"
$env:OCR_AUTO_TESSERACT_MIN_TOKENS = "20"
$env:OCR_PADDLE_USE_ANGLE = "false"
$env:OLLAMA_MODE = "glossary_only"
$env:OLLAMA_TIMEOUT_SECONDS = "120"
$env:OLLAMA_NUM_PREDICT = "220"
$env:OLLAMA_GLOSSARY_NUM_PREDICT = "260"
$env:OLLAMA_NUM_CTX = "1024"
$env:OLLAMA_TEMPERATURE = "0.1"

Write-Host "Starting FastAPI on http://127.0.0.1:$Port" -ForegroundColor Green
python -m uvicorn app.main:app --host 127.0.0.1 --port $Port --reload
