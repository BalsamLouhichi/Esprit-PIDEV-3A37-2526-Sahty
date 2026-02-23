import io
import os
import re
import statistics
import unicodedata
from dataclasses import dataclass, field

import fitz
import pytesseract
from PIL import Image, ImageFilter, ImageOps
from pytesseract import Output, TesseractNotFoundError

from .schemas import ExtractedTest, StructuredExtraction


class LayoutParserError(Exception):
    pass


@dataclass
class Token:
    text: str
    x0: float
    x1: float
    y0: float
    y1: float
    page: int
    conf: float = 1.0

    @property
    def x_center(self) -> float:
        return (self.x0 + self.x1) / 2.0

    @property
    def y_center(self) -> float:
        return (self.y0 + self.y1) / 2.0

    @property
    def height(self) -> float:
        return max(1.0, self.y1 - self.y0)


@dataclass
class VisualLine:
    page: int
    y: float
    tokens: list[Token] = field(default_factory=list)

    @property
    def text(self) -> str:
        return " ".join(t.text for t in sorted(self.tokens, key=lambda tk: tk.x0)).strip()


@dataclass
class ColumnDef:
    name: str
    x_min: float
    x_max: float


@dataclass
class ParsedDocument:
    ocr_text: str
    pages: int
    engine: str
    structured: StructuredExtraction


_PADDLE_OCR_CACHE: dict[str, object] = {}


def _normalize(value: str) -> str:
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode("ascii")
    value = value.lower()
    value = re.sub(r"\s+", " ", value).strip()
    return value


def _is_pdf(file_bytes: bytes, filename: str) -> bool:
    return (filename or "").lower().endswith(".pdf") or file_bytes.startswith(b"%PDF")


def detect_pdf_textual(file_bytes: bytes, min_words: int = 40, min_alpha_ratio: float = 0.3) -> bool:
    if not file_bytes.startswith(b"%PDF"):
        return False
    try:
        with fitz.open(stream=file_bytes, filetype="pdf") as doc:
            total = 0
            alpha = 0
            for page in doc:
                words = page.get_text("words")
                total += len(words)
                for w in words:
                    txt = str(w[4]).strip() if len(w) > 4 else ""
                    if re.search(r"[A-Za-z]", txt):
                        alpha += 1
            if total < min_words:
                return False
            return (alpha / max(1, total)) >= min_alpha_ratio
    except Exception:
        return False


def _extract_native_pdf_tokens(file_bytes: bytes) -> tuple[list[Token], int, float]:
    tokens: list[Token] = []
    page_count = 0
    max_width = 0.0
    with fitz.open(stream=file_bytes, filetype="pdf") as doc:
        page_count = len(doc)
        for p_idx, page in enumerate(doc, start=1):
            max_width = max(max_width, float(page.rect.width))
            words = page.get_text("words")
            for w in words:
                if len(w) < 5:
                    continue
                x0, y0, x1, y1, txt = float(w[0]), float(w[1]), float(w[2]), float(w[3]), str(w[4]).strip()
                if not txt:
                    continue
                tokens.append(Token(text=txt, x0=x0, x1=x1, y0=y0, y1=y1, page=p_idx, conf=1.0))
    return tokens, page_count, max_width


def _get_pdf_scale() -> float:
    try:
        scale = float(os.getenv("OCR_PDF_SCALE", "2.0"))
    except ValueError:
        scale = 2.0
    return min(max(scale, 1.3), 2.4)


def _pdf_to_images(file_bytes: bytes) -> list[Image.Image]:
    images: list[Image.Image] = []
    scale = _get_pdf_scale()
    with fitz.open(stream=file_bytes, filetype="pdf") as doc:
        for page in doc:
            pix = page.get_pixmap(matrix=fitz.Matrix(scale, scale), alpha=False)
            images.append(Image.frombytes("RGB", [pix.width, pix.height], pix.samples))
    return images


def _tesseract_variants(image: Image.Image) -> list[Image.Image]:
    base = image.convert("RGB")
    gray = ImageOps.grayscale(base)
    enhanced = ImageOps.autocontrast(gray)
    denoised = enhanced.filter(ImageFilter.MedianFilter(size=3))
    sharp = denoised.filter(ImageFilter.SHARPEN)
    bw = sharp.point(lambda p: 255 if p > 175 else 0)
    return [base, sharp.convert("RGB"), bw.convert("RGB")]


def _tokens_from_tesseract_data(data: dict, page: int) -> list[Token]:
    tokens: list[Token] = []
    n = len(data.get("text", []))
    for i in range(n):
        txt = str(data["text"][i]).strip()
        if not txt:
            continue
        conf_raw = str(data.get("conf", ["-1"])[i]).strip()
        try:
            conf = float(conf_raw)
        except ValueError:
            conf = -1.0
        if conf < 0:
            continue

        x, y = float(data["left"][i]), float(data["top"][i])
        w, h = float(data["width"][i]), float(data["height"][i])
        tokens.append(Token(text=txt, x0=x, x1=x + w, y0=y, y1=y + h, page=page, conf=conf / 100.0))
    return tokens


def _ocr_quality_score(tokens: list[Token]) -> float:
    if not tokens:
        return 0.0
    n = len(tokens)
    avg_conf = sum(t.conf for t in tokens) / n
    informative = sum(1 for t in tokens if re.search(r"[A-Za-z0-9]", t.text))
    numeric = sum(1 for t in tokens if re.search(r"\d", t.text))
    inf_ratio = informative / n
    num_ratio = numeric / n
    volume = min(1.0, n / 120.0)
    return (0.60 * avg_conf) + (0.20 * inf_ratio) + (0.10 * num_ratio) + (0.10 * volume)


def _ocr_tesseract_tokens(image: Image.Image, page: int, lang: str) -> list[Token]:
    fast_mode = os.getenv("OCR_TESSERACT_FAST", "true").strip().lower() in {"1", "true", "yes", "on"}
    if fast_mode:
        try:
            variant = _tesseract_variants(image)[1]
            data = pytesseract.image_to_data(
                variant,
                lang=lang,
                output_type=Output.DICT,
                config="--oem 1 --psm 6 -c preserve_interword_spaces=1",
            )
            return _tokens_from_tesseract_data(data, page)
        except TesseractNotFoundError as exc:
            raise LayoutParserError("Tesseract not found in PATH.") from exc
        except Exception as exc:
            raise LayoutParserError(f"Tesseract OCR failed: {exc}") from exc

    configs = [
        "--oem 3 --psm 6 -c preserve_interword_spaces=1",
        "--oem 3 --psm 11 -c preserve_interword_spaces=1",
    ]
    best_tokens: list[Token] = []
    best_score = -1.0

    try:
        variants = _tesseract_variants(image)
        for variant in variants:
            for cfg in configs:
                data = pytesseract.image_to_data(variant, lang=lang, output_type=Output.DICT, config=cfg)
                tokens = _tokens_from_tesseract_data(data, page)
                score = _ocr_quality_score(tokens)
                if score > best_score:
                    best_score = score
                    best_tokens = tokens
    except TesseractNotFoundError as exc:
        raise LayoutParserError("Tesseract not found in PATH.") from exc
    except Exception as exc:
        raise LayoutParserError(f"Tesseract OCR failed: {exc}") from exc

    return best_tokens


def _get_paddle_ocr(lang: str):
    try:
        from paddleocr import PaddleOCR
    except ImportError as exc:
        raise LayoutParserError("PaddleOCR not installed. Install requirements-paddle.txt.") from exc

    paddle_lang = "fr" if "fr" in lang.lower() else "en"
    cached = _PADDLE_OCR_CACHE.get(paddle_lang)
    if cached is not None:
        return cached

    use_angle = os.getenv("OCR_PADDLE_USE_ANGLE", "false").strip().lower() in {"1", "true", "yes", "on"}
    ocr = PaddleOCR(use_angle_cls=use_angle, lang=paddle_lang, show_log=False)
    _PADDLE_OCR_CACHE[paddle_lang] = ocr
    return ocr


def _ocr_paddle_tokens(image: Image.Image, page: int, lang: str) -> list[Token]:
    try:
        import numpy as np
    except ImportError as exc:
        raise LayoutParserError("PaddleOCR dependencies are not installed.") from exc

    ocr = _get_paddle_ocr(lang)
    result = ocr.ocr(np.array(image), cls=True)

    tokens: list[Token] = []
    for block in result:
        if not block:
            continue
        for item in block:
            if len(item) < 2:
                continue
            box = item[0]
            text_conf = item[1]
            if not isinstance(text_conf, (list, tuple)) or len(text_conf) < 2:
                continue
            txt = str(text_conf[0]).strip()
            conf = float(text_conf[1])
            if not txt:
                continue
            xs = [float(pt[0]) for pt in box]
            ys = [float(pt[1]) for pt in box]
            tokens.append(
                Token(
                    text=txt,
                    x0=min(xs),
                    x1=max(xs),
                    y0=min(ys),
                    y1=max(ys),
                    page=page,
                    conf=conf,
                )
            )
    return tokens


def ocr_with_boxes(file_bytes: bytes, filename: str, engine: str = "tesseract", lang: str = "fra+eng") -> tuple[list[Token], int, float, str]:
    selected = (engine or "tesseract").lower().strip()
    if selected not in {"tesseract", "paddle", "auto"}:
        raise LayoutParserError("Invalid OCR engine (use tesseract|paddle|auto).")

    if _is_pdf(file_bytes, filename):
        images = _pdf_to_images(file_bytes)
    else:
        images = [Image.open(io.BytesIO(file_bytes)).convert("RGB")]

    tokens: list[Token] = []
    max_width = 0.0
    used_engine = "tesseract"

    for idx, image in enumerate(images, start=1):
        max_width = max(max_width, float(image.width))
        if selected == "paddle":
            tokens.extend(_ocr_paddle_tokens(image, idx, lang))
            used_engine = "paddle"
        elif selected == "tesseract":
            tokens.extend(_ocr_tesseract_tokens(image, idx, lang))
            used_engine = "tesseract"
        else:
            tesseract_tokens: list[Token] = []
            try:
                tesseract_tokens = _ocr_tesseract_tokens(image, idx, lang)
            except Exception:
                tesseract_tokens = []

            t_score = _ocr_quality_score(tesseract_tokens)
            auto_min_score = float(os.getenv("OCR_AUTO_TESSERACT_MIN_SCORE", "0.50"))
            auto_min_tokens = int(os.getenv("OCR_AUTO_TESSERACT_MIN_TOKENS", "20"))
            if tesseract_tokens and t_score >= auto_min_score and len(tesseract_tokens) >= auto_min_tokens:
                tokens.extend(tesseract_tokens)
                used_engine = "tesseract"
                continue

            paddle_tokens: list[Token] = []
            try:
                paddle_tokens = _ocr_paddle_tokens(image, idx, lang)
            except Exception:
                paddle_tokens = []

            p_score = _ocr_quality_score(paddle_tokens)
            if p_score > t_score and paddle_tokens:
                tokens.extend(paddle_tokens)
                used_engine = "paddle"
            elif tesseract_tokens:
                tokens.extend(tesseract_tokens)
                used_engine = "tesseract"
            elif paddle_tokens:
                tokens.extend(paddle_tokens)
                used_engine = "paddle"
            else:
                raise LayoutParserError("Auto OCR failed: no tokens extracted.")

    return tokens, len(images), max_width, used_engine


def group_by_lines(tokens: list[Token], y_tolerance: float | None = None) -> list[VisualLine]:
    if not tokens:
        return []

    by_page: dict[int, list[Token]] = {}
    for token in tokens:
        by_page.setdefault(token.page, []).append(token)

    lines: list[VisualLine] = []
    for page in sorted(by_page.keys()):
        page_tokens = sorted(by_page[page], key=lambda t: (t.y_center, t.x0))
        heights = [t.height for t in page_tokens]
        auto_tol = y_tolerance if y_tolerance is not None else max(2.5, statistics.median(heights) * 0.6)

        page_lines: list[VisualLine] = []
        for token in page_tokens:
            placed = False
            for line in page_lines:
                if abs(token.y_center - line.y) <= auto_tol:
                    line.tokens.append(token)
                    line.y = (line.y + token.y_center) / 2.0
                    placed = True
                    break
            if not placed:
                page_lines.append(VisualLine(page=page, y=token.y_center, tokens=[token]))

        for line in page_lines:
            line.tokens = sorted(line.tokens, key=lambda t: t.x0)
        lines.extend(sorted(page_lines, key=lambda l: l.y))

    return lines


def _find_header_line(lines: list[VisualLine]) -> tuple[int, dict[str, float]] | tuple[None, dict[str, float]]:
    keymap = {
        "name": ("examen", "analyse", "test", "parametre"),
        "value": ("valeur", "resultat", "result"),
        "unit": ("uni", "unite", "unit"),
        "reference": ("reference", "vr", "norme", "plage"),
        "flag": ("flag", "alerte"),
    }

    best_idx = None
    best_anchors: dict[str, float] = {}
    best_score = 0

    for idx, line in enumerate(lines[:120]):
        anchors: dict[str, float] = {}
        for token in line.tokens:
            norm = _normalize(token.text)
            for cname, keys in keymap.items():
                if cname in anchors:
                    continue
                if any(k in norm for k in keys):
                    anchors[cname] = token.x_center

        score = len(anchors)
        if score >= 2 and score > best_score:
            best_score = score
            best_idx = idx
            best_anchors = anchors

    return best_idx, best_anchors


def _fallback_anchors(page_width: float) -> dict[str, float]:
    w = max(800.0, page_width)
    return {
        "name": 0.18 * w,
        "value": 0.48 * w,
        "unit": 0.63 * w,
        "reference": 0.79 * w,
        "flag": 0.92 * w,
    }


def _kmeans_1d(values: list[float], k: int, max_iter: int = 16) -> list[float]:
    if not values or k <= 0 or len(values) < k:
        return []

    ordered = sorted(values)
    n = len(ordered)
    centroids = [ordered[min(n - 1, int((i + 0.5) * n / k))] for i in range(k)]

    for _ in range(max_iter):
        groups: list[list[float]] = [[] for _ in range(k)]
        for v in ordered:
            idx = min(range(k), key=lambda i: abs(v - centroids[i]))
            groups[idx].append(v)

        new_centroids: list[float] = []
        for i in range(k):
            if groups[i]:
                new_centroids.append(sum(groups[i]) / len(groups[i]))
            else:
                new_centroids.append(centroids[i])

        delta = max(abs(new_centroids[i] - centroids[i]) for i in range(k))
        centroids = new_centroids
        if delta < 0.5:
            break

    centroids = sorted(centroids)
    deduped: list[float] = []
    for c in centroids:
        if not deduped or abs(c - deduped[-1]) > 8:
            deduped.append(c)
    return deduped


def _anchors_from_x_clusters(lines: list[VisualLine], page_width: float) -> dict[str, float]:
    # Build 1D x samples mostly from table-like lines.
    xs: list[float] = []
    for line in lines[:220]:
        txt = line.text
        has_number = bool(re.search(r"\d", txt))
        if not has_number:
            continue
        if not (2 <= len(line.tokens) <= 12):
            continue
        for token in line.tokens:
            xs.append(token.x_center)

    if len(xs) < 20:
        return {}

    # Prefer 5 columns: name | value | unit | reference | flag
    centroids = _kmeans_1d(xs, 5)
    if len(centroids) < 4:
        centroids = _kmeans_1d(xs, 4)
    if len(centroids) < 4:
        return {}

    names_5 = ["name", "value", "unit", "reference", "flag"]
    names_4 = ["name", "value", "unit", "reference"]
    names = names_5 if len(centroids) >= 5 else names_4

    anchors: dict[str, float] = {}
    for i, cname in enumerate(names):
        anchors[cname] = centroids[i]

    # Clamp to page bounds for safety.
    for k, v in list(anchors.items()):
        anchors[k] = min(max(0.0, v), page_width)
    return anchors


def _detect_columns(lines: list[VisualLine], page_width: float) -> tuple[list[ColumnDef], int]:
    header_idx, anchors = _find_header_line(lines)
    clustered = _anchors_from_x_clusters(lines, page_width)
    fb = _fallback_anchors(page_width)

    if not anchors:
        anchors = clustered or {}
    else:
        for k, v in clustered.items():
            anchors.setdefault(k, v)

    for k, v in fb.items():
        anchors.setdefault(k, v)

    ordered = sorted(anchors.items(), key=lambda kv: kv[1])
    bounds: list[tuple[str, float, float]] = []
    for i, (name, x) in enumerate(ordered):
        left = 0.0 if i == 0 else (ordered[i - 1][1] + x) / 2.0
        right = page_width if i == len(ordered) - 1 else (x + ordered[i + 1][1]) / 2.0
        bounds.append((name, left, right))

    cols = [ColumnDef(name=n, x_min=l, x_max=r) for n, l, r in bounds]
    return cols, (-1 if header_idx is None else header_idx)


def split_into_columns(line_tokens: list[Token], columns: list[ColumnDef]) -> dict[str, str]:
    parts: dict[str, list[str]] = {c.name: [] for c in columns}
    for token in sorted(line_tokens, key=lambda t: t.x0):
        xc = token.x_center
        target = None
        for col in columns:
            if col.x_min <= xc < col.x_max:
                target = col.name
                break
        if target is None:
            target = columns[-1].name
        parts[target].append(token.text)
    return {k: " ".join(v).strip() if v else "" for k, v in parts.items()}


def _parse_ref(reference_text: str | None) -> tuple[float | None, float | None]:
    if not reference_text:
        return None, None
    n = _normalize(reference_text)
    nums = [s.replace(" ", "") for s in re.findall(r"\d[\d\s]*(?:[.,]\d+)?", n)]
    vals: list[float] = []
    for item in nums:
        try:
            vals.append(float(item.replace(",", ".")))
        except ValueError:
            continue
    if "<" in n and vals:
        return None, vals[-1]
    if ">" in n and vals:
        return vals[0], None
    if len(vals) >= 2:
        return vals[0], vals[1]
    return None, None


def _parse_value(value_raw: str | None) -> float | None:
    if not value_raw:
        return None
    txt = value_raw.strip().replace(",", ".")
    if txt.startswith("."):
        return None
    m = re.search(r"[-+]?\d*\.?\d+", txt.replace(" ", ""))
    if not m:
        return None
    try:
        return float(m.group(0))
    except ValueError:
        return None


def _status(value_num: float | None, ref_low: float | None, ref_high: float | None) -> str:
    if value_num is None or (ref_low is None and ref_high is None):
        return "UNKNOWN"
    if ref_low is not None and value_num < ref_low:
        return "LOW"
    if ref_high is not None and value_num > ref_high:
        return "HIGH"
    return "NORMAL"


def _normalize_test_name(name: str) -> str:
    n = _normalize(name)
    synonyms = {
        "alt": "ALAT",
        "alat": "ALAT",
        "ast": "ASAT",
        "asat": "ASAT",
        "wbc": "Leucocytes",
        "hb": "Hemoglobine",
        "plt": "Numeration des plaquettes",
    }
    for key, canon in synonyms.items():
        if key in n:
            return canon
    # title-case fallback
    cleaned = name.strip()
    return cleaned[:1].upper() + cleaned[1:] if cleaned else cleaned


def build_rows(lines: list[VisualLine], columns: list[ColumnDef], header_idx: int = -1) -> list[ExtractedTest]:
    rows: list[ExtractedTest] = []
    start = header_idx + 1 if header_idx >= 0 else 0

    for line in lines[start:]:
        cols = split_into_columns(line.tokens, columns)
        name_raw = cols.get("name", "").strip().strip(":")
        value_raw = cols.get("value", "").strip()
        unit = cols.get("unit", "").strip() or None
        reference_text = cols.get("reference", "").strip() or None
        flag = cols.get("flag", "").strip() or None

        if not any([name_raw, value_raw, unit, reference_text, flag]):
            continue

        # drop obvious meta lines
        nn = _normalize(name_raw)
        if any(k in nn for k in ("patient", "prescripteur", "date", "note test", "rapport")):
            continue
        if nn in {"examen", "analyse", "test"}:
            continue

        # require test-like name to create a row
        if not re.search(r"[A-Za-z]", name_raw):
            continue

        name = _normalize_test_name(name_raw)
        value_num = _parse_value(value_raw or None)
        ref_low, ref_high = _parse_ref(reference_text)
        st = _status(value_num, ref_low, ref_high)

        notes: list[str] = []
        needs_review = False
        if not value_raw:
            notes.append("Missing value.")
            needs_review = True
        if not reference_text:
            notes.append("Missing reference.")
            needs_review = True
        if value_raw and value_raw.startswith("."):
            notes.append("Value OCR uncertain.")
            needs_review = True

        if flag:
            notes.append(f"Flag={flag}")

        # Incoherence guardrail: Hb with ref around leucocyte range (e.g. 4-10) => reject mapping.
        nname = _normalize(name)
        if "hemoglob" in nname and ref_high is not None and ref_high <= 10.5:
            needs_review = True
            st = "UNKNOWN"
            notes.append("Incoherent Hb reference; mapping rejected.")
            reference_text = None
            ref_low = None
            ref_high = None

        confidence = 0.3
        if value_raw and reference_text and unit and not needs_review:
            confidence = 0.9
        elif value_raw and reference_text and not needs_review:
            confidence = 0.6

        rows.append(
            ExtractedTest(
                name=name,
                value_raw=value_raw or None,
                value_num=value_num,
                unit=unit,
                reference_text=reference_text,
                ref_low=ref_low,
                ref_high=ref_high,
                status=st,
                needs_review=needs_review or st == "UNKNOWN",
                confidence=confidence,
                notes=notes,
                flag=flag,
            )
        )

    return rows


def validate_rows(rows: list[ExtractedTest]) -> tuple[list[ExtractedTest], list[str]]:
    warnings: list[str] = []
    if not rows:
        return rows, ["No rows parsed from layout."]

    by_name: dict[str, ExtractedTest] = {}
    for row in rows:
        key = _normalize(row.name)
        # keep richer row
        old = by_name.get(key)
        if old is None:
            by_name[key] = row
            continue
        old_score = (1 if old.value_raw else 0) + (1 if old.reference_text else 0) + (1 if old.unit else 0)
        new_score = (1 if row.value_raw else 0) + (1 if row.reference_text else 0) + (1 if row.unit else 0)
        if new_score > old_score:
            by_name[key] = row

    validated = list(by_name.values())

    # Extra domain guardrails against obvious column shifts.
    for row in validated:
        n = _normalize(row.name)
        if row.reference_text and row.ref_high is not None:
            if "hemoglob" in n and row.ref_high <= 10.5:
                row.notes.append("Hb reference not plausible; reference detached.")
                row.reference_text = None
                row.ref_low = None
                row.ref_high = None
                row.status = "UNKNOWN"
                row.needs_review = True
            elif "leucocyte" in n and row.ref_high <= 1.5:
                row.notes.append("Leucocyte reference not plausible; reference detached.")
                row.reference_text = None
                row.ref_low = None
                row.ref_high = None
                row.status = "UNKNOWN"
                row.needs_review = True
            elif "plaquette" in n and row.ref_high <= 20:
                row.notes.append("Platelet reference not plausible; reference detached.")
                row.reference_text = None
                row.ref_low = None
                row.ref_high = None
                row.status = "UNKNOWN"
                row.needs_review = True

    missing_refs = sum(1 for r in validated if not r.reference_text)
    if missing_refs:
        warnings.append(f"{missing_refs} row(s) missing reference.")
    unknown = sum(1 for r in validated if r.status == "UNKNOWN")
    if unknown:
        warnings.append(f"{unknown} row(s) have UNKNOWN status and require review.")

    return validated, warnings


def _tokens_to_text(lines: list[VisualLine]) -> str:
    chunks: list[str] = []
    current_page = None
    for line in lines:
        if current_page != line.page:
            current_page = line.page
            chunks.append(f"--- PAGE {current_page} ---")
        chunks.append(line.text)
    return "\n".join(chunks).strip()


def parse_document_with_layout(file_bytes: bytes, filename: str, ocr_engine: str = "tesseract", lang: str = "fra+eng") -> ParsedDocument:
    if not file_bytes:
        raise LayoutParserError("Empty file.")

    tokens: list[Token]
    pages = 1
    page_width = 1000.0
    engine_used = "native-pdf"

    if _is_pdf(file_bytes, filename) and detect_pdf_textual(file_bytes):
        tokens, pages, page_width = _extract_native_pdf_tokens(file_bytes)
        engine_used = "native-pdf"
    else:
        tokens, pages, page_width, used = ocr_with_boxes(file_bytes, filename, engine=ocr_engine, lang=lang)
        engine_used = f"ocr-{used}"

    if not tokens:
        raise LayoutParserError("No tokens with coordinates extracted.")

    lines = group_by_lines(tokens)
    columns, header_idx = _detect_columns(lines, page_width)
    rows = build_rows(lines, columns, header_idx=header_idx)
    rows, warnings = validate_rows(rows)

    text = _tokens_to_text(lines)
    doc_type = "hematologie" if "hematologie" in _normalize(text) else "unknown"
    structured = StructuredExtraction(
        document_type=doc_type,
        tests=rows,
        global_needs_review=any(r.needs_review for r in rows) if rows else True,
        warnings=warnings,
    )
    return ParsedDocument(ocr_text=text, pages=pages, engine=engine_used, structured=structured)
