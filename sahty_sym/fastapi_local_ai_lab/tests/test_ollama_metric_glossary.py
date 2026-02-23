import unittest

from app.ollama_service import _normalize_metric_glossary


class TestOllamaMetricGlossary(unittest.TestCase):
    def test_normalize_metric_glossary_from_dict(self) -> None:
        payload = {
            "metric_glossary": {
                "CRP": "  Marqueur d'inflammation.  ",
                "ASAT": "Enzyme utilisee pour completer le bilan hepatique.",
                "Inconnu": "Doit etre ignore.",
            }
        }

        glossary = _normalize_metric_glossary(payload, ["CRP", "ASAT"])

        self.assertEqual(set(glossary.keys()), {"CRP", "ASAT"})
        self.assertEqual(glossary["CRP"], "Marqueur d'inflammation.")

    def test_normalize_metric_glossary_from_list_and_accents(self) -> None:
        payload = {
            "metric_glossary": [
                {"name": "Hémoglobine", "description": "Transport de l'oxygene via les globules rouges."},
                {"name": "Créatinine", "description": "Aide a apprecier la fonction renale."},
            ]
        }

        glossary = _normalize_metric_glossary(payload, ["Hemoglobine", "Creatinine"])

        self.assertIn("Hemoglobine", glossary)
        self.assertIn("Creatinine", glossary)

    def test_normalize_metric_glossary_empty_when_no_known_name(self) -> None:
        payload = {"metric_glossary": {"Autre": "Description"}}
        glossary = _normalize_metric_glossary(payload, ["CRP"])
        self.assertEqual(glossary, {})
