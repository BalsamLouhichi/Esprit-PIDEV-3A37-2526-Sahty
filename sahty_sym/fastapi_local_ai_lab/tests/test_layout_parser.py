import os
import unittest
from pathlib import Path

from app.layout_row_parser import LayoutParserError, detect_pdf_textual, parse_document_with_layout


def _find_existing_path(env_key: str, fallbacks: list[Path]) -> Path | None:
    env_value = os.getenv(env_key, "").strip()
    if env_value:
        p = Path(env_value)
        if p.exists() and p.is_file():
            return p

    for p in fallbacks:
        if p.exists() and p.is_file():
            return p
    return None


class LayoutParserPdfTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        home = Path.home()
        root = Path(__file__).resolve().parents[1]
        cls.textual_pdf = _find_existing_path(
            "BILAN_TEXT_PDF_PATH",
            [
                home / "Downloads" / "bilan_test_textuel.pdf",
                root / "samples" / "bilan_test_textuel.pdf",
            ],
        )
        cls.scan_pdf = _find_existing_path(
            "BILAN_SCAN_PDF_PATH",
            [
                home / "Downloads" / "bilan2.pdf",
                root / "samples" / "bilan2.pdf",
            ],
        )

    def test_textual_pdf_native_layout_parse(self) -> None:
        if self.textual_pdf is None:
            self.skipTest("bilan_test_textuel.pdf not found. Set BILAN_TEXT_PDF_PATH.")

        file_bytes = self.textual_pdf.read_bytes()
        self.assertTrue(detect_pdf_textual(file_bytes))

        parsed = parse_document_with_layout(file_bytes, self.textual_pdf.name, ocr_engine="tesseract", lang="fra+eng")
        self.assertEqual(parsed.engine, "native-pdf")
        self.assertGreaterEqual(len(parsed.structured.tests), 1)

        complete_rows = [t for t in parsed.structured.tests if t.value_raw and t.reference_text]
        self.assertGreaterEqual(len(complete_rows), 1)

        for test in parsed.structured.tests:
            if not test.value_raw or not test.reference_text:
                self.assertTrue(test.needs_review)

    def test_scan_pdf_layout_parse_with_boxes(self) -> None:
        if self.scan_pdf is None:
            self.skipTest("bilan2.pdf not found. Set BILAN_SCAN_PDF_PATH.")

        file_bytes = self.scan_pdf.read_bytes()
        try:
            parsed = parse_document_with_layout(file_bytes, self.scan_pdf.name, ocr_engine="tesseract", lang="fra+eng")
        except LayoutParserError as exc:
            if "Tesseract not found" in str(exc):
                self.skipTest(str(exc))
            raise

        self.assertIn(parsed.engine, {"ocr-tesseract", "ocr-paddle", "native-pdf"})
        self.assertGreaterEqual(len(parsed.structured.tests), 1)

        status_values = {t.status for t in parsed.structured.tests}
        self.assertTrue(any(s in status_values for s in {"NORMAL", "HIGH", "LOW", "UNKNOWN"}))

        for test in parsed.structured.tests:
            if not test.value_raw or not test.reference_text:
                self.assertTrue(test.needs_review)


if __name__ == "__main__":
    unittest.main()
