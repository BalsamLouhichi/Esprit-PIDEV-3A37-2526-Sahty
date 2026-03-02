<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuizQuestionReformulationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function reformulateForPatient(string $question, ?string $quizName = null): string
    {
        $normalizedQuestion = trim($question);
        if ($normalizedQuestion === '') {
            throw new \InvalidArgumentException('Question vide.');
        }

        $apiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? ''));
        $model = trim((string) ($_ENV['QUIZ_REFORMULATION_MODEL'] ?? $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'));
        $result = $this->fallbackReformulation($normalizedQuestion);

        if ($apiKey !== '') {
            $systemPrompt = 'Tu reformules des questions de quiz sante pour des patients. '
                . 'Conserve strictement le sens medical, le niveau de gravite et la temporalite. '
                . 'Utilise un francais simple, clair, sans jargon. '
                . 'Reponds avec UNE seule phrase, sans puces, sans guillemets, sans explication.';

            $userPrompt = sprintf(
                "Quiz: %s\nQuestion originale: %s\n\nReformule pour un patient qui ne comprend pas bien.",
                $quizName ? trim($quizName) : 'Quiz de sante',
                $normalizedQuestion
            );

            try {
                $content = $this->requestReformulatedContent($apiKey, $model, $systemPrompt, $userPrompt);
                if ($content !== '') {
                    $result = $this->normalizeOutput($content, $normalizedQuestion);
                }
            } catch (\Throwable) {
                $result = $this->fallbackReformulation($normalizedQuestion);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractAssistantContent(array $data): string
    {
        $content = $data['choices'][0]['message']['content'] ?? null;
        $extracted = $this->extractContentFromMessage($content);
        if ($extracted === '') {
            $extracted = $this->extractFallbackContent($data);
        }

        return $extracted;
    }

    private function normalizeOutput(string $raw, string $fallback): string
    {
        $text = trim($raw);
        $text = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B\"'");

        if ($text === '') {
            return $this->fallbackReformulation($fallback);
        }

        $parts = preg_split('/(?<=[\.\!\?])\s+/', $text) ?: [];
        if (count($parts) > 1) {
            $text = trim((string) $parts[0]);
        }

        if (mb_strlen($text) > 280) {
            $text = mb_substr($text, 0, 280);
            $text = rtrim($text, " ,.;:") . '...';
        }

        return $text !== '' ? $text : $this->fallbackReformulation($fallback);
    }

    private function fallbackReformulation(string $question): string
    {
        return 'En termes simples: ' . $question;
    }

    private function requestReformulatedContent(
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userPrompt
    ): string {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 120,
            ],
            'timeout' => 20,
        ]);

        $content = '';
        $statusCode = $response->getStatusCode();
        $data = $response->toArray(false);
        if ($statusCode < 400 && is_array($data)) {
            $content = $this->extractAssistantContent($data);
        }

        return $content;
    }

    private function extractContentFromMessage(mixed $content): string
    {
        $extracted = '';

        if (is_string($content)) {
            $extracted = trim($content);
        } elseif (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                $piece = $this->extractTextFromMessagePart($item);
                if ($piece !== '') {
                    $parts[] = $piece;
                }
            }
            if ($parts !== []) {
                $extracted = trim(implode(' ', $parts));
            }
        }

        return $extracted;
    }

    private function extractTextFromMessagePart(mixed $item): string
    {
        $text = '';

        if (is_string($item)) {
            $text = trim($item);
        } elseif (is_array($item)) {
            $candidate = $item['text'] ?? $item['content'] ?? $item['value'] ?? null;
            if (is_string($candidate)) {
                $text = trim($candidate);
            }
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractFallbackContent(array $data): string
    {
        $fallbackContent = $data['choices'][0]['text'] ?? $data['output_text'] ?? null;

        return is_string($fallbackContent) ? trim($fallbackContent) : '';
    }
}

