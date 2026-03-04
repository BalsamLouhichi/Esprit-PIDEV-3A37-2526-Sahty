<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiQuizGeneratorService
{
    private const ALLOWED_TYPES = ['likert_0_4', 'likert_1_5', 'yes_no'];
    private const ALLOWED_CATEGORIES = ['stress', 'anxiete', 'sommeil', 'concentration', 'humeur'];

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{name: string, description: string, questions: array<int, array{text: string, type: string, category: string, reverse: bool}>}
     */
    public function generateFromCategory(string $category): array
    {
        $normalizedCategory = $this->normalizeCategory($category);

        $apiKey = (string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '');
        $model = (string) ($_ENV['OPENAI_MODEL'] ?? $_SERVER['OPENAI_MODEL'] ?? 'gpt-4o-mini');

        if ($apiKey === '') {
            return $this->fallbackQuiz($normalizedCategory);
        }

        $payload = [
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en psychoeducation. Reponds uniquement en JSON valide.',
                ],
                [
                    'role' => 'user',
                    'content' => sprintf(
                        'Genere un quiz medical non diagnostique en francais pour la categorie "%s". Retourne strictement un objet JSON avec: name (string), description (string), questions (array de 8 objets). Chaque question: text (string), type (likert_0_4), category (%s), reverse (boolean). Les questions doivent etre claires, courtes, et utiles.',
                        $normalizedCategory,
                        $normalizedCategory
                    ),
                ],
            ],
            'temperature' => 0.6,
        ];

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 30,
        ]);

        $data = $response->toArray(false);
        $rawContent = $data['choices'][0]['message']['content'] ?? '';
        if (!is_string($rawContent) || trim($rawContent) === '') {
            return $this->fallbackQuiz($normalizedCategory);
        }

        $decoded = json_decode($this->stripCodeFences($rawContent), true);
        if (!is_array($decoded)) {
            return $this->fallbackQuiz($normalizedCategory);
        }

        $sanitized = $this->sanitizeGeneratedQuiz($decoded, $normalizedCategory);
        if (count($sanitized['questions']) === 0) {
            return $this->fallbackQuiz($normalizedCategory);
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array{name: string, description: string, questions: array<int, array{text: string, type: string, category: string, reverse: bool}>}
     */
    private function sanitizeGeneratedQuiz(array $decoded, string $category): array
    {
        $name = trim((string) ($decoded['name'] ?? 'Quiz ' . ucfirst($category)));
        $description = trim((string) ($decoded['description'] ?? 'Quiz genere automatiquement pour la categorie ' . $category . '.'));
        $questions = [];

        $rawQuestions = $decoded['questions'] ?? [];
        if (is_array($rawQuestions)) {
            foreach ($rawQuestions as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $text = trim((string) ($item['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                $type = (string) ($item['type'] ?? 'likert_0_4');
                if (!in_array($type, self::ALLOWED_TYPES, true)) {
                    $type = 'likert_0_4';
                }

                $itemCategory = $this->normalizeCategory((string) ($item['category'] ?? $category));
                $reverse = (bool) ($item['reverse'] ?? false);

                $questions[] = [
                    'text' => $text,
                    'type' => $type,
                    'category' => $itemCategory,
                    'reverse' => $reverse,
                ];
            }
        }

        return [
            'name' => $name,
            'description' => $description,
            'questions' => array_slice($questions, 0, 12),
        ];
    }

    /**
     * @return array{name: string, description: string, questions: array<int, array{text: string, type: string, category: string, reverse: bool}>}
     */
    private function fallbackQuiz(string $category): array
    {
        $titleByCategory = [
            'stress' => 'Evaluation du Niveau de Stress',
            'anxiete' => 'Evaluation de l Anxiete Quotidienne',
            'sommeil' => 'Evaluation de la Qualite du Sommeil',
            'concentration' => 'Evaluation de la Concentration',
            'humeur' => 'Evaluation de l Humeur',
        ];

        $descriptionByCategory = [
            'stress' => 'Questionnaire court pour evaluer le stress recent et ses impacts.',
            'anxiete' => 'Questionnaire pour evaluer le niveau d inquietude et de tension.',
            'sommeil' => 'Questionnaire pour analyser les habitudes et la qualite du sommeil.',
            'concentration' => 'Questionnaire pour evaluer l attention et la concentration.',
            'humeur' => 'Questionnaire pour evaluer l humeur generale sur les derniers jours.',
        ];

        $questionBank = [
            'stress' => [
                'Vous sentez-vous submerge par vos responsabilites ?',
                'Avez-vous du mal a vous detendre en fin de journee ?',
                'Vous sentez-vous irritable sans raison claire ?',
                'Avez-vous des tensions physiques liees au stress ?',
                'Vous sentez-vous presse par le temps toute la journee ?',
                'Avez-vous des difficultes a deconnecter du travail ?',
                'Vous vous sentez fatigue mentalement ?',
                'Vous gardez un bon controle de vos emotions ?',
            ],
            'anxiete' => [
                'Avez-vous des inquietudes difficiles a controler ?',
                'Vous sentez-vous nerveux ou tendu ?',
                'Avez-vous des pensees negatives repetitives ?',
                'Vous evitez des situations par apprehension ?',
                'Avez-vous des difficultes a rester calme ?',
                'Vous anticipez souvent le pire scenario ?',
                'Avez-vous des sensations physiques d anxiete ?',
                'Vous parvenez a relativiser les situations stressantes ?',
            ],
            'sommeil' => [
                'Avez-vous du mal a vous endormir ?',
                'Vous reveillez-vous pendant la nuit ?',
                'Vous vous sentez repose au reveil ?',
                'Avez-vous un rythme de coucher regulier ?',
                'Avez-vous des difficultes a vous rendormir apres un reveil ?',
                'Votre sommeil est-il perturbe par des pensees ?',
                'Consommez-vous des ecrans avant de dormir ?',
                'Votre energie est-elle bonne dans la journee ?',
            ],
            'concentration' => [
                'Avez-vous des difficultes a rester concentre longtemps ?',
                'Vous dispersez-vous facilement pendant une tache ?',
                'Avez-vous du mal a terminer ce que vous commencez ?',
                'Avez-vous besoin de relire plusieurs fois une information ?',
                'Vous oubliez des details importants ?',
                'Avez-vous du mal a organiser vos priorites ?',
                'Vous parvenez a travailler sans distraction ?',
                'Vous suivez facilement une consigne jusqu au bout ?',
            ],
            'humeur' => [
                'Vous sentez-vous demotive ces derniers jours ?',
                'Avez-vous moins de plaisir dans vos activites habituelles ?',
                'Votre humeur varie-t-elle fortement dans la journee ?',
                'Vous sentez-vous souvent triste ou vide ?',
                'Avez-vous des difficultes a garder une vision positive ?',
                'Vous vous sentez fatigue sans raison apparente ?',
                'Avez-vous de la facilite a garder de l espoir ?',
                'Vous sentez-vous soutenu par votre entourage ?',
            ],
        ];

        $questions = [];
        foreach ($questionBank[$category] ?? $questionBank['stress'] as $index => $text) {
            $questions[] = [
                'text' => $text,
                'type' => 'likert_0_4',
                'category' => $category,
                'reverse' => str_contains($text, 'parvenez') || str_contains($text, 'controle') || str_contains($text, 'repose'),
            ];
            if ($index >= 7) {
                break;
            }
        }

        return [
            'name' => $titleByCategory[$category] ?? ('Quiz ' . ucfirst($category)),
            'description' => $descriptionByCategory[$category] ?? ('Quiz genere pour la categorie ' . $category . '.'),
            'questions' => $questions,
        ];
    }

    private function normalizeCategory(string $category): string
    {
        $value = mb_strtolower(trim($category));
        $map = [
            'anxiety' => 'anxiete',
            'anxiete' => 'anxiete',
            'anxiété' => 'anxiete',
            'stress' => 'stress',
            'sleep' => 'sommeil',
            'sommeil' => 'sommeil',
            'focus' => 'concentration',
            'attention' => 'concentration',
            'concentration' => 'concentration',
            'mood' => 'humeur',
            'humeur' => 'humeur',
            'bien_etre' => 'humeur',
            'bien-etre' => 'humeur',
            'bien etre' => 'humeur',
        ];

        $normalized = $map[$value] ?? $value;
        if (!in_array($normalized, self::ALLOWED_CATEGORIES, true)) {
            return 'stress';
        }

        return $normalized;
    }

    private function stripCodeFences(string $content): string
    {
        $trimmed = trim($content);
        if (!str_starts_with($trimmed, '```')) {
            return $trimmed;
        }

        $trimmed = preg_replace('/^```[a-zA-Z0-9_]*\s*/', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        return trim($trimmed);
    }
}
