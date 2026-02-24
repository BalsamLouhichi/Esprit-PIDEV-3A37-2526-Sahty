import io
import os
from dataclasses import dataclass

import fitz
import pytesseract
from PIL import Image
from pytesseract import TesseractNotFoundError


@dataclass
class OCRExtraction:
    text: str
    pages: int
    engine: str


class OCRServiceError(Exception):
    pass


_PADDLE_OCR_CACHE: dict[str, object] = {}


def _is_pdf(file_bytes: bytes, filename: str) -> bool:
    lower_name = (filename or "").lower()
    return lower_name.endswith(".pdf") or file_bytes.startswith(b"%PDF")


def _extract_with_tesseract(image: Image.Image, lang: str) -> str:
    try:
        return pytesseract.image_to_string(image, lang=lang)
    except TesseractNotFoundError as exc:
        raise OCRServiceError(
            "Tesseract is not installed or not in PATH. "
            "Install it locally and retry."
        ) from exc


def _extract_with_paddle(image: Image.Image, lang: str) -> str:
    try:
        import numpy as np
        from paddleocr import PaddleOCR
    except ImportError as exc:
        raise OCRServiceError(
            "PaddleOCR is not installed. Install requirements-paddle.txt first."
        ) from exc

    paddle_lang = "en"
    if "fr" in lang:
        paddle_lang = "fr"

    ocr = _PADDLE_OCR_CACHE.get(paddle_lang)
    if ocr is None:
        use_angle = os.getenv("OCR_PADDLE_USE_ANGLE", "false").strip().lower() in {"1", "true", "yes", "on"}
        ocr = PaddleOCR(use_angle_cls=use_angle, lang=paddle_lang, show_log=False)
        _PADDLE_OCR_CACHE[paddle_lang] = ocr

    result = ocr.ocr(np.array(image), cls=True)
    chunks: list[str] = []
    for block in result:
        if not block:
            continue
        for line in block:
            if len(line) > 1 and isinstance(line[1], (list, tuple)) and line[1]:
                chunks.append(str(line[1][0]))
    return "\n".join(chunks).strip()


def _extract_from_image(image: Image.Image, engine: str, lang: str) -> str:
    if engine == "paddle":
        return _extract_with_paddle(image, lang)
    if engine == "tesseract":
        return _extract_with_tesseract(image, lang)

    # auto mode: tesseract first, paddle fallback
    try:
        return _extract_with_tesseract(image, lang)
    except OCRServiceError:
        return _extract_with_paddle(image, lang)


def _pdf_to_images(file_bytes: bytes) -> list[Image.Image]:
    images: list[Image.Image] = []
    try:
        scale = float(os.getenv("OCR_PDF_SCALE", "2.0"))
    except ValueError:
        scale = 2.0
    scale = min(max(scale, 1.3), 2.4)

    with fitz.open(stream=file_bytes, filetype="pdf") as doc:
        for page in doc:
            pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False)
            image = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
            images.append(image)
    return images


def extract_text(
    file_bytes: bytes,
    filename: str,
    engine: str = "tesseract",
    lang: str = "fra+eng",
) -> OCRExtraction:
    if not file_bytes:
        raise OCRServiceError("Empty file.")

    selected_engine = engine.lower().strip()
    if selected_engine not in {"auto", "tesseract", "paddle"}:
        raise OCRServiceError("Invalid OCR engine. Use auto, tesseract, or paddle.")

    texts: list[str] = []
    pages = 1

    if _is_pdf(file_bytes, filename):
        images = _pdf_to_images(file_bytes)
        pages = len(images)
        for idx, img in enumerate(images, start=1):
            page_text = _extract_from_image(img, selected_engine, lang).strip()
            if page_text:
                texts.append(f"--- PAGE {idx} ---\n{page_text}")
    else:
        image = Image.open(io.BytesIO(file_bytes)).convert("RGB")
        page_text = _extract_from_image(image, selected_engine, lang).strip()
        if page_text:
            texts.append(page_text)

    full_text = "\n\n".join(texts).strip()
    if not full_text:
        raise OCRServiceError("No text extracted. Check file quality or OCR engine.")

    used_engine = selected_engine if selected_engine != "auto" else "auto"
    return OCRExtraction(text=full_text, pages=pages, engine=used_engine)
