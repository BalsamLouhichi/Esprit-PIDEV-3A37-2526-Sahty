<?php

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\Utilisateur;

class QuizPdfReportService
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const FONT_SIZE = 11;
    private const LINE_HEIGHT = 15;
    private const LEFT_MARGIN = 50;
    private const TOP_Y = 800;
    private const MAX_LINE_LENGTH = 92;

    /**
     * @param array<int, array{reco: object, selectedVideo: ?string}> $recommendationItems
     * @param array<string,mixed>|null $aiRecommendation
     */
    public function buildResultPdf(
        Quiz $quiz,
        ?Utilisateur $user,
        int $score,
        int $maxScore,
        int $percentage,
        array $recommendationItems,
        ?array $aiRecommendation = null
    ): string {
        $patientName = $user ? trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')) : 'Patient';
        $createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i');
        $lines = $this->buildHeaderLines($quiz, $patientName, $createdAt, $score, $maxScore, $percentage);
        $this->appendRecommendationSection($lines, $recommendationItems);
        $this->appendAiRecommendationSection($lines, $aiRecommendation);

        return $this->buildSimplePdf($this->normalizeLines($lines));
    }

    /**
     * @return string[]
     */
    private function buildHeaderLines(
        Quiz $quiz,
        string $patientName,
        string $createdAt,
        int $score,
        int $maxScore,
        int $percentage
    ): array {
        return [
            'SAHTY - Quiz Report',
            'Generated at: ' . $createdAt,
            '',
            'Patient: ' . ($patientName !== '' ? $patientName : 'Patient'),
            'Quiz: ' . $quiz->getName(),
            'Score: ' . $score . ' / ' . $maxScore . ' (' . $percentage . '%)',
            '',
            'Recommendations:',
        ];
    }

    /**
     * @param array<int, array{reco: object, selectedVideo: ?string}> $recommendationItems
     * @param string[] $lines
     */
    private function appendRecommendationSection(array &$lines, array $recommendationItems): void
    {
        if (empty($recommendationItems)) {
            $lines[] = '- No specific recommendation for this score.';
        } else {
            foreach ($recommendationItems as $item) {
                $this->appendRecommendationItemLines($lines, $item);
            }
        }
    }

    /**
     * @param array{reco: object, selectedVideo: ?string} $item
     * @param string[] $lines
     */
    private function appendRecommendationItemLines(array &$lines, array $item): void
    {
        $reco = $item['reco'] ?? null;
        if (!$reco || !method_exists($reco, 'getTitle')) {
            return;
        }

        $title = (string) ($reco->getTitle() ?: $reco->getName());
        $severity = $this->normalizeSeverityLabel((string) $reco->getSeverity());
        $description = trim((string) ($reco->getDescription() ?? ''));
        $video = trim((string) ($item['selectedVideo'] ?? ''));

        $lines[] = '- [' . $severity . '] ' . $title;
        if ($description !== '') {
            foreach ($this->wrapText($description, 95) as $wrapped) {
                $lines[] = '  ' . $wrapped;
            }
        }
        if ($video !== '') {
            $lines[] = '  Video: ' . $video;
        }
        $lines[] = '';
    }

    /**
     * @param string[] $lines
     * @param array<string,mixed>|null $aiRecommendation
     */
    private function appendAiRecommendationSection(array &$lines, ?array $aiRecommendation): void
    {
        if (!is_array($aiRecommendation)) {
            return;
        }

        $summary = trim((string) ($aiRecommendation['summary'] ?? ''));
        if ($summary === '') {
            return;
        }

        $lines[] = '';
        $lines[] = 'AI Recommendation:';
        $lines[] = $summary;

        $this->appendAiRecommendationRows($lines, $aiRecommendation['recommendations'] ?? []);
        $this->appendAiVideoRows($lines, $aiRecommendation['videos'] ?? []);
        $this->appendAiDisclaimer($lines, (string) ($aiRecommendation['disclaimer'] ?? ''));
    }

    /**
     * @param string[] $lines
     */
    private function appendAiRecommendationRows(array &$lines, mixed $rows): void
    {
        if (!is_array($rows) || $rows === []) {
            return;
        }

        foreach ($rows as $row) {
            if (!is_scalar($row) || trim((string) $row) === '') {
                continue;
            }
            $lines[] = '- ' . trim((string) $row);
        }
    }

    /**
     * @param string[] $lines
     */
    private function appendAiVideoRows(array &$lines, mixed $videoRows): void
    {
        if (!is_array($videoRows) || $videoRows === []) {
            return;
        }

        $lines[] = '';
        $lines[] = 'AI Suggested YouTube Videos:';

        foreach ($videoRows as $video) {
            if (!is_array($video)) {
                continue;
            }
            $title = trim((string) ($video['title'] ?? 'YouTube video'));
            $url = trim((string) ($video['url'] ?? ''));
            $channel = trim((string) ($video['channel_hint'] ?? ''));
            if ($url === '') {
                continue;
            }
            $line = '- ' . ($title !== '' ? $title : 'YouTube video');
            if ($channel !== '') {
                $line .= ' (' . $channel . ')';
            }
            $lines[] = $line;
            $lines[] = '  Link: ' . $url;
        }
    }

    /**
     * @param string[] $lines
     */
    private function appendAiDisclaimer(array &$lines, string $disclaimer): void
    {
        $trimmed = trim($disclaimer);
        if ($trimmed === '') {
            return;
        }

        $lines[] = '';
        $lines[] = 'IA Disclaimer: ' . $trimmed;
    }

    /**
     * @param string[] $lines
     */
    private function buildSimplePdf(array $lines): string
    {
        $linesPerPage = (int) floor((self::TOP_Y - 60) / self::LINE_HEIGHT);
        if ($linesPerPage < 1) {
            $linesPerPage = 40;
        }

        $lineChunks = array_chunk($lines, $linesPerPage);
        if (count($lineChunks) === 0) {
            $lineChunks = [['No content']];
        }

        $objects = [];
        $catalogObjectId = 1;
        $pagesObjectId = 2;
        $fontObjectId = 3;

        $objects[$catalogObjectId] = "<< /Type /Catalog /Pages {$pagesObjectId} 0 R >>";
        $objects[$fontObjectId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pageObjectIds = [];
        $contentObjectIds = [];
        $nextObjectId = 4;
        for ($index = 0; $index < count($lineChunks); $index++) {
            $pageObjectIds[] = $nextObjectId++;
            $contentObjectIds[] = $nextObjectId++;
        }

        $kids = [];
        foreach ($pageObjectIds as $pageObjectId) {
            $kids[] = $pageObjectId . ' 0 R';
        }
        $objects[$pagesObjectId] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . '] /Count ' . count($pageObjectIds) . " >>";

        foreach ($lineChunks as $index => $chunk) {
            $pageObjectId = $pageObjectIds[$index];
            $contentObjectId = $contentObjectIds[$index];
            $stream = $this->buildPageStream($chunk);

            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                $pagesObjectId,
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $fontObjectId,
                $contentObjectId
            );
            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        ksort($objects);
        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObjectId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObjectId; $i++) {
            $offset = $offsets[$i] ?? 0;
            $state = isset($offsets[$i]) ? 'n' : 'f';
            $gen = isset($offsets[$i]) ? '00000' : '65535';
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . ' ' . $gen . ' ' . $state . " \n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root {$catalogObjectId} 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    /**
     * @param string[] $lines
     */
    private function buildPageStream(array $lines): string
    {
        $stream = "BT\n";
        $stream .= '/F1 ' . self::FONT_SIZE . " Tf\n";
        $stream .= self::LINE_HEIGHT . " TL\n";
        $stream .= '1 0 0 1 ' . self::LEFT_MARGIN . ' ' . self::TOP_Y . " Tm\n";

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $stream .= "T*\n";
            }
            $stream .= '(' . $this->escapePdfText($line) . ") Tj\n";
        }

        $stream .= "ET\n";
        return $stream;
    }

    /**
     * @param string[] $lines
     * @return string[]
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                $normalized[] = '';
                continue;
            }

            foreach ($this->wrapText($line, self::MAX_LINE_LENGTH) as $wrapped) {
                $normalized[] = $wrapped;
            }
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function wrapText(string $text, int $width): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $result = [];
        $words = explode(' ', $text);
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : ($line . ' ' . $word);
            if (mb_strlen($candidate) <= $width) {
                $line = $candidate;
                continue;
            }

            if ($line !== '') {
                $result[] = $line;
            }
            $line = $word;
        }

        if ($line !== '') {
            $result[] = $line;
        }

        return $result;
    }

    private function escapePdfText(string $value): string
    {
        $value = $this->toPrintableAscii($value);
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
        $value = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return $value;
    }

    private function normalizeSeverityLabel(string $severity): string
    {
        $normalized = mb_strtolower(trim($severity));
        $label = strtoupper($this->toPrintableAscii($severity));

        $rules = [
            'ELEVE' => ['high', 'elev'],
            'MOYEN' => ['medium', 'moy'],
            'FAIBLE' => ['low', 'faib'],
        ];

        foreach ($rules as $candidateLabel => $tokens) {
            foreach ($tokens as $token) {
                if (str_contains($normalized, $token)) {
                    $label = $candidateLabel;
                    break 2;
                }
            }
        }

        return $label;
    }

    private function toPrintableAscii(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            return $transliterated;
        }

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? $value;
    }
}
