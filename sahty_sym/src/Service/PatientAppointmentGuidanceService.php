<?php

namespace App\Service;

use App\Entity\FicheMedicale;
use App\Entity\RendezVous;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PatientAppointmentGuidanceService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(RendezVous $rendezVous): array
    {
        $motif = trim((string) ($rendezVous->getRaison() ?? ''));
        $motifNormalized = $this->normalize($motif);
        $fiche = $this->resolveRelevantFiche($rendezVous);
        $antecedents = trim((string) ($fiche?->getAntecedents() ?? ''));
        $allergies = trim((string) ($fiche?->getAllergies() ?? ''));
        $traitements = trim((string) ($fiche?->getTraitementEnCours() ?? ''));

        $fullContext = $this->normalize($motif . ' ' . $antecedents . ' ' . $allergies . ' ' . $traitements);

        if ($this->isGenericFollowUpMotif($motifNormalized)) {
            return $this->buildFollowUpGuidance($motif, $antecedents, $allergies, $traitements);
        }

        $quality = $this->evaluateMotifQuality($motifNormalized);
        if (!$quality['valid']) {
            return $this->buildInsufficientInputGuidance(
                $motif,
                $antecedents,
                $allergies,
                $traitements,
                $quality['reason']
            );
        }

        $motifSpecific = $this->buildMotifSpecificGuidance(
            $motifNormalized,
            $motif,
            $antecedents,
            $allergies,
            $traitements,
            $fullContext
        );
        if ($motifSpecific !== null) {
            return $motifSpecific;
        }

        $aiResult = $this->generateWithAi($motif, $antecedents, $allergies, $traitements, $fullContext);
        if ($aiResult !== null) {
            return [
                'disclaimer' => 'Guidance preventive uniquement en attendant le medecin. Ce contenu ne remplace pas un diagnostic medical ni une prescription.',
                'temporary_advice' => $this->normalizeStringList($aiResult['temporary_advice'] ?? []),
                'safety_alerts' => $this->normalizeStringList($aiResult['safety_alerts'] ?? []),
                'general_recommendations' => $this->normalizeStringList($aiResult['general_recommendations'] ?? []),
                'emergency' => $this->normalizeEmergency($aiResult['emergency'] ?? []),
                'sources' => [
                    'motif' => $motif,
                    'antecedents' => $antecedents,
                    'allergies' => $allergies,
                    'traitements' => $traitements,
                ],
                'input_status' => 'ok',
            ];
        }

        $temporaryAdvice = $this->buildTemporaryAdvice($fullContext);
        $safetyAlerts = $this->buildSafetyAlerts($fullContext, $allergies, $traitements);
        $generalRecommendations = $this->buildGeneralRecommendations($motif, $allergies, $traitements, $fullContext);
        $emergency = $this->detectEmergency($fullContext);

        return [
            'disclaimer' => 'Guidance preventive uniquement en attendant le medecin. Ce contenu ne remplace pas un diagnostic medical ni une prescription.',
            'temporary_advice' => $temporaryAdvice,
            'safety_alerts' => $safetyAlerts,
            'general_recommendations' => $generalRecommendations,
            'emergency' => $emergency,
            'sources' => [
                'motif' => $motif,
                'antecedents' => $antecedents,
                'allergies' => $allergies,
                'traitements' => $traitements,
            ],
            'input_status' => 'ok',
        ];
    }

    /**
     * @return array{valid: bool, reason: string}
     */
    private function evaluateMotifQuality(string $motifNormalized): array
    {
        if ($motifNormalized === '') {
            return ['valid' => false, 'reason' => 'Motif vide'];
        }

        if (mb_strlen($motifNormalized, 'UTF-8') < 8) {
            return ['valid' => false, 'reason' => 'Motif trop court'];
        }

        if (preg_match('/([a-z0-9])\1{4,}/', $motifNormalized) === 1) {
            return ['valid' => false, 'reason' => 'Texte repetitif detecte'];
        }

        $words = array_values(array_filter(explode(' ', $motifNormalized), static fn (string $w): bool => $w !== ''));
        if (count($words) < 2) {
            return ['valid' => false, 'reason' => 'Motif peu descriptif'];
        }

        $meaningless = ['ok', 'test', 'valide', 'validation', 'bonjour', 'salut', 'rien'];
        if (count(array_diff($words, $meaningless)) < 2) {
            return ['valid' => false, 'reason' => 'Motif non medical'];
        }

        $medicalKeywords = [
            'douleur', 'fievre', 'toux', 'gorge', 'migraine', 'maux', 'mal', 'vomissement',
            'nausee', 'vertige', 'fatigue', 'diarrhee', 'constipation', 'allergie', 'respirer',
            'essoufflement', 'palpitation', 'thoracique', 'abdominale', 'dos', 'rhume', 'grippe',
            'infection', 'plaie', 'saignement', 'pression', 'tension', 'glycemie', 'suivi', 'controle',
        ];
        if (!$this->containsAny($motifNormalized, $medicalKeywords) && count($words) < 4) {
            return ['valid' => false, 'reason' => 'Motif insuffisamment medical'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    private function isGenericFollowUpMotif(string $motifNormalized): bool
    {
        if ($motifNormalized === '') {
            return false;
        }

        $followUpTerms = [
            'suivi',
            'suivi normal',
            'controle',
            'checkup',
            'consultation de suivi',
            'visite de controle',
            'renouvellement',
        ];
        if (!$this->containsAny($motifNormalized, $followUpTerms)) {
            return false;
        }

        $acuteTerms = [
            'douleur', 'fievre', 'toux', 'vomissement', 'nausee', 'vertige',
            'saignement', 'essoufflement', 'thoracique', 'abdominale', 'infection',
        ];

        return !$this->containsAny($motifNormalized, $acuteTerms);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFollowUpGuidance(
        string $motif,
        string $antecedents,
        string $allergies,
        string $traitements
    ): array {
        $alerts = [];
        if (trim($allergies) !== '') {
            $alerts[] = 'Signalez vos allergies au medecin au debut de la consultation.';
        }

        return [
            'disclaimer' => 'Guidance informative avant une consultation de suivi. Ne remplace pas un avis medical.',
            'temporary_advice' => [
                'Prenez vos ordonnances et vos derniers resultats (analyses, imagerie, comptes rendus).',
            ],
            'safety_alerts' => $alerts,
            'general_recommendations' => [
                'Preparez 2-3 questions principales pour votre medecin.',
                'Notez les changements depuis la derniere consultation (amelioration, aggravation, effets indesirables).',
            ],
            'emergency' => [
                'detected' => false,
                'level' => 'LOW',
                'reasons' => [],
                'actions' => ['Si un nouveau symptome important apparait, contactez rapidement le SAMU (190) en Tunisie.'],
            ],
            'sources' => [
                'motif' => $motif,
                'antecedents' => $antecedents,
                'allergies' => $allergies,
                'traitements' => $traitements,
            ],
            'input_status' => 'ok',
        ];
    }

    /**
     * Guidance deterministe orientee par motif (plus stable que la generation libre).
     */
    /**
     * @return array<string, mixed>|null
     */
    private function buildMotifSpecificGuidance(
        string $motifNormalized,
        string $motif,
        string $antecedents,
        string $allergies,
        string $traitements,
        string $fullContext
    ): ?array {
        if ($motifNormalized === '') {
            return null;
        }

        $profiles = [
            'respiratoire' => [
                'keywords' => ['toux', 'gorge', 'rhume', 'grippe', 'essoufflement', 'respirer', 'sinus'],
                'advice' => ['Hydratez-vous regulierement et notez la frequence de la toux/essoufflement.'],
                'reco' => ['Precisez depuis quand les symptomes respiratoires ont commence et ce qui les aggrave.'],
                'urgent' => ['Si essoufflement important ou douleur thoracique, contactez le SAMU (190) '],
            ],
            'digestif' => [
                'keywords' => ['abdominale', 'abdomen', 'ventre', 'diarrhee', 'vomissement', 'nausee', 'gastro'],
                'advice' => ['Fractionnez l alimentation et maintenez une hydratation orale frequente.'],
                'reco' => ['Notez le nombre de selles/vomissements et la presence de sang ou fievre.'],
                'urgent' => ['Si douleur abdominale intense persistante ou signes de dehydration, contactez le SAMU (190).'],
            ],
            'douleur' => [
                'keywords' => ['douleur', 'migraine', 'mal de tete', 'cephalee', 'dos', 'lombaire', 'articulation'],
                'advice' => ['Limitez les efforts et notez l intensite de la douleur (0-10) et sa duree.'],
                'reco' => ['Indiquez les facteurs declenchants et ce qui soulage/aggrave la douleur.'],
                'urgent' => ['Si douleur brutale inhabituelle avec malaise, contactez le SAMU (190).'],
            ],
            'dermatologie' => [
                'keywords' => ['peau', 'eruption', 'rash', 'demangeaison', 'plaie', 'rougeur'],
                'advice' => ['Evitez les nouveaux produits cutanes et gardez la zone propre et seche.'],
                'reco' => ['Prenez une photo de l evolution de la lesion pour la consultation.'],
                'urgent' => ['Si gonflement du visage, gene respiratoire ou extension rapide, contactez le SAMU (190).'],
            ],
            'cardio' => [
                'keywords' => ['thoracique', 'palpitation', 'coeur', 'tension', 'hypertension'],
                'advice' => ['Evitez l effort physique intense et reposez-vous en attendant la consultation.'],
                'reco' => ['Notez l heure de debut des symptomes et les circonstances (repos/effort/stress).'],
                'urgent' => ['Douleur thoracique oppressante ou essoufflement aigu: appelez le SAMU (190) immediatement.'],
            ],
            'gyneco' => [
                'keywords' => ['gyneco', 'regles', 'menstru', 'pelvienne', 'grossesse', 'enceinte'],
                'advice' => ['Notez la date des dernieres regles et l evolution des symptomes pelviens.'],
                'reco' => ['Signalez tout saignement anormal, retard de regles ou douleur inhabituelle.'],
                'urgent' => ['En cas de saignement abondant ou douleur intense, contactez le SAMU (190).'],
            ],
        ];

        foreach ($profiles as $profile) {
            if (!$this->containsAny($motifNormalized, $profile['keywords'])) {
                continue;
            }

            $alerts = [];
            if (trim($allergies) !== '') {
                $alerts[] = 'Allergies declarees: informez le medecin avant toute prise de medicament.';
            }
            if ($alerts === []) {
                $alerts[] = 'Evitez l automedication jusqu a l avis du medecin.';
            }

            return [
                'disclaimer' => 'Guidance orientee selon votre motif. Ne remplace pas une consultation medicale.',
                'temporary_advice' => $profile['advice'],
                'safety_alerts' => $alerts,
                'general_recommendations' => $profile['reco'],
                'emergency' => [
                    'detected' => false,
                    'level' => 'LOW',
                    'reasons' => [],
                    'actions' => $profile['urgent'],
                ],
                'sources' => [
                    'motif' => $motif,
                    'antecedents' => $antecedents,
                    'allergies' => $allergies,
                    'traitements' => $traitements,
                ],
                'input_status' => 'ok',
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInsufficientInputGuidance(
        string $motif,
        string $antecedents,
        string $allergies,
        string $traitements,
        string $reason
    ): array {
        return [
            'disclaimer' => 'Motif insuffisant pour afficher une guidance pre-consultation.',
            'temporary_advice' => [
                'Indiquez le symptome principal.',
                'Precisez depuis quand il a commence et son intensite.',
            ],
            'safety_alerts' => [
                'En cas de douleur thoracique, detresse respiratoire, perte de connaissance ou saignement important: appelez le SAMU (190) en Tunisie.',
            ],
            'general_recommendations' => [
                'Reformulez votre motif en une phrase claire et medicale.',
                'Evitez les mots de test (ex: ok, valide, test).',
            ],
            'emergency' => [
                'detected' => false,
                'level' => 'LOW',
                'reasons' => [$reason],
                'actions' => ['Mettez a jour le motif pour obtenir une guidance pre-consultation plus precise.'],
            ],
            'sources' => [
                'motif' => $motif,
                'antecedents' => $antecedents,
                'allergies' => $allergies,
                'traitements' => $traitements,
            ],
            'input_status' => 'insufficient',
        ];
    }

    private function resolveRelevantFiche(RendezVous $rendezVous): ?FicheMedicale
    {
        if ($rendezVous->getFicheMedicale() instanceof FicheMedicale) {
            return $rendezVous->getFicheMedicale();
        }

        $patient = $rendezVous->getPatient();
        if ($patient === null) {
            return null;
        }

        $latest = null;
        foreach ($patient->getFicheMedicales() as $fiche) {
            if (!$fiche instanceof FicheMedicale) {
                continue;
            }
            if ($latest === null) {
                $latest = $fiche;
                continue;
            }

            $currentDate = $fiche->getCreeLe();
            $latestDate = $latest->getCreeLe();
            if ($currentDate !== null && ($latestDate === null || $currentDate > $latestDate)) {
                $latest = $fiche;
            }
        }

        return $latest;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(["\n", "\r", "\t"], ' ', $text);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = strtolower($ascii);
        }
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;

        return preg_replace('/\s+/', ' ', trim($text)) ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function buildTemporaryAdvice(string $context): array
    {
        $advice = [];

        if ($this->containsAny($context, ['fievre', 'temperature', 'grippe', 'frisson'])) {
            $advice[] = 'Hydratez-vous regulierement et surveillez la temperature 2 a 3 fois par jour.';
            $advice[] = 'Reposez-vous et evitez l effort physique intense jusqu a la consultation.';
        }

        if ($this->containsAny($context, ['toux', 'gorge', 'rhume', 'sinus', 'angine'])) {
            $advice[] = 'Evitez la fumee et les irritants; aerer la piece peut aider.';
        }

        if ($this->containsAny($context, ['douleur', 'migraine', 'mal de tete', 'cephalee'])) {
            $advice[] = 'Notez l intensite de la douleur (0-10), sa duree et les facteurs declenchants.';
        }

        if ($this->containsAny($context, ['diarrhee', 'vomissement', 'nausee', 'gastro'])) {
            $advice[] = 'Fractionnez l alimentation et privilegiez une hydratation orale frequente.';
        }

        if ($this->containsAny($context, ['peau', 'eruption', 'rash', 'demangeaison'])) {
            $advice[] = 'Evitez les nouveaux produits cutanes en attendant l avis medical.';
        }

        if ($this->containsAny($context, ['douleur thoracique', 'palpitation', 'coeur'])) {
            $advice[] = 'Evitez les efforts et le stress physique en attendant l evaluation medicale.';
        }

        if ($this->containsAny($context, ['douleur ventre', 'douleur abdominale', 'abdomen'])) {
            $advice[] = 'Evitez les repas lourds et suivez l evolution de la douleur dans le temps.';
        }

        if ($advice === []) {
            $advice[] = 'Conservez un rythme de repos regulier et preparez les informations utiles pour votre consultation.';
            $advice[] = 'Surveillez l evolution des symptomes et notez toute aggravation.';
        }

        return array_values(array_unique($advice));
    }

    /**
     * @return array<int, string>
     */
    private function buildSafetyAlerts(string $context, string $allergies, string $traitements): array
    {
        $alerts = [];
        $allergiesNorm = $this->normalize($allergies);
        $traitementsNorm = $this->normalize($traitements);

        if ($allergiesNorm !== '') {
            $alerts[] = 'Allergies declarees detectees: informez le medecin avant toute adaptation de traitement.';
        }

        if ($this->containsAny($allergiesNorm, ['penicilline', 'amoxicilline']) && $this->containsAny($context, ['angine', 'infection'])) {
            $alerts[] = 'Allergie potentielle aux beta-lactamines: signalez-la explicitement au medecin avant tout antibiotique.';
        }

        if ($this->containsAny($traitementsNorm, ['anticoagul', 'warfarine', 'heparine', 'apixaban', 'rivaroxaban'])) {
            $alerts[] = 'Traitement anticoagulant detecte: toute chute, saignement ou traumatisme impose une vigilance accrue.';
        }

        if ($this->containsAny($context, ['grossesse', 'enceinte'])) {
            $alerts[] = 'Contexte de grossesse mentionne: evitez toute prise medicamenteuse sans validation medicale.';
        }

        if ($this->containsAny($context, ['diabete']) && $this->containsAny($context, ['vomissement', 'diarrhee', 'fievre'])) {
            $alerts[] = 'Diabete + symptomes intercurrents: surveillez glycemie/hydratation et contactez rapidement un professionnel si desequilibre.';
        }

        if ($alerts === []) {
            $alerts[] = 'Alerte majeure automatique non detectee, mais signalez tout antecedent important pendant la consultation.';
        }

        return array_values(array_unique($alerts));
    }

    /**
     * @return array<int, string>
     */
    private function buildGeneralRecommendations(string $motif, string $allergies, string $traitements, string $context): array
    {
        $recommendations = [
            'Preparez une liste chronologique des symptomes (debut, frequence, intensite).',
            'Apportez vos ordonnances, resultats recents et la liste de vos traitements en cours.',
        ];

        if (trim($allergies) !== '') {
            $recommendations[] = 'Mentionnez vos allergies des le debut de la consultation.';
        }
        if (trim($traitements) !== '') {
            $recommendations[] = 'Ne stoppez pas un traitement chronique sans avis medical.';
        }
        if (trim($motif) !== '') {
            $recommendations[] = 'Si les symptomes changent avant le RDV, mettez a jour votre motif pour aider le medecin.';
        }
        if ($this->containsAny($context, ['fievre', 'toux', 'gorge'])) {
            $recommendations[] = 'Pensez a signaler duree de la fievre/toux et les medicaments deja pris.';
        }
        if ($this->containsAny($context, ['douleur thoracique', 'essoufflement', 'palpitation'])) {
            $recommendations[] = 'Indiquez les circonstances exactes de declenchement (repos, effort, stress).';
        }
        if ($this->containsAny($context, ['diabete', 'hypertension', 'asthme'])) {
            $recommendations[] = 'Apportez vos mesures recentes (glycemie, tension, debit respiratoire si disponible).';
        }

        return array_values(array_unique($recommendations));
    }

    /**
     * @return array{detected: bool, level: string, reasons: array<int, string>, actions: array<int, string>}
     */
    private function detectEmergency(string $context): array
    {
        $reasons = [];
        $actions = [];

        $rules = [
            'douleur thoracique intense' => ['douleur thoracique', 'oppression thoracique'],
            'difficulte respiratoire severe' => ['essoufflement', 'difficulte a respirer', 'manque d air'],
            'signe neurologique aigu' => ['faiblesse d un cote', 'paralysie', 'trouble de la parole', 'confusion brutale'],
            'perte de connaissance/convulsions' => ['perte de connaissance', 'syncope', 'convulsion'],
            'hemorragie active importante' => ['saignement abondant', 'hemorragie'],
            'ideation suicidaire' => ['idee suicidaire', 'envie de mourir', 'suicide'],
        ];

        foreach ($rules as $label => $keywords) {
            if ($this->containsAny($context, $keywords)) {
                $reasons[] = $label;
            }
        }

        $detected = $reasons !== [];
        if ($detected) {
            $actions[] = 'Contactez immediatement le SAMU (190) en Tunisie si les symptomes sont en cours/aggravation.';
            $actions[] = 'Ne restez pas seul et evitez de conduire vous-meme.';
            $actions[] = 'Preparez la liste de vos traitements/allergies pour les urgences.';
        } else {
            $actions[] = 'Aucune urgence vitale automatique detectee selon les informations actuelles.';
            $actions[] = 'Si aggravation rapide, re-evaluez immediatement via les urgences.';
        }

        return [
            'detected' => $detected,
            'level' => $detected ? 'HIGH' : 'LOW',
            'reasons' => $reasons,
            'actions' => $actions,
        ];
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        if ($text === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *   temporary_advice: array<int, string>,
     *   safety_alerts: array<int, string>,
     *   general_recommendations: array<int, string>,
     *   emergency: array{detected: bool, level: string, reasons: array<int, string>, actions: array<int, string>}
     * }|null
     */
    private function generateWithAi(string $motif, string $antecedents, string $allergies, string $traitements, string $fullContext): ?array
    {
        $provider = strtolower((string) ($_ENV['APP_AI_GUIDANCE_PROVIDER'] ?? 'local'));
        if (!in_array($provider, ['openai', 'huggingface'], true)) {
            return null;
        }

        $endpoint = trim((string) ($_ENV['APP_AI_GUIDANCE_ENDPOINT'] ?? ''));
        $apiKey = trim((string) ($_ENV['APP_AI_GUIDANCE_API_KEY'] ?? ''));
        $model = trim((string) ($_ENV['APP_AI_GUIDANCE_MODEL'] ?? ''));
        if ($endpoint === '' || $apiKey === '' || $model === '') {
            return null;
        }

        $systemPrompt = 'Tu es un assistant medical de pre-guidance patient. Interdiction de prescrire des traitements. Reponds uniquement en JSON.';
        $userPrompt = sprintf(
            "Contexte patient avant rendez-vous:\n".
            "- motif: %s\n".
            "- antecedents: %s\n".
            "- allergies: %s\n".
            "- traitements en cours: %s\n\n".
            "Objectif: guidance preventive temporaire en attendant le medecin.\n".
            "Retourne STRICTEMENT un JSON avec ce schema:\n".
            "{\n".
            "  \"temporary_advice\": [\"...\"],\n".
            "  \"safety_alerts\": [\"...\"],\n".
            "  \"general_recommendations\": [\"...\"],\n".
            "  \"emergency\": {\"detected\": bool, \"level\": \"LOW|HIGH\", \"reasons\": [\"...\"], \"actions\": [\"...\"]}\n".
            "}\n".
            "Regles:\n".
            "- Conseils clairs, concrets, non prescriptifs.\n".
            "- Detecter urgence seulement si signaux forts.\n".
            "- Reponse breve: 1 a 2 elements max par liste.\n".
            "- Langue: francais.",
            $motif !== '' ? $motif : '(non renseigne)',
            $antecedents !== '' ? $antecedents : '(non renseigne)',
            $allergies !== '' ? $allergies : '(non renseigne)',
            $traitements !== '' ? $traitements : '(non renseigne)'
        );

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
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
                    'max_tokens' => 900,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
            if ($statusCode >= 400) {
                return null;
            }

            $content = (string) ($data['choices'][0]['message']['content'] ?? '');
            $decoded = $this->parseJsonFromText($content);
            if (!is_array($decoded)) {
                return null;
            }

            return [
                'temporary_advice' => $this->normalizeStringList($decoded['temporary_advice'] ?? []),
                'safety_alerts' => $this->normalizeStringList($decoded['safety_alerts'] ?? []),
                'general_recommendations' => $this->normalizeStringList($decoded['general_recommendations'] ?? []),
                'emergency' => $this->normalizeEmergency($decoded['emergency'] ?? []),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param mixed $value
     * @return array{detected: bool, level: string, reasons: array<int, string>, actions: array<int, string>}
     */
    private function normalizeEmergency(mixed $value): array
    {
        if (!is_array($value)) {
            return [
                'detected' => false,
                'level' => 'LOW',
                'reasons' => [],
                'actions' => [],
            ];
        }

        $detected = (bool) ($value['detected'] ?? false);
        $level = strtoupper(trim((string) ($value['level'] ?? 'LOW')));
        if (!in_array($level, ['LOW', 'HIGH'], true)) {
            $level = $detected ? 'HIGH' : 'LOW';
        }

        return [
            'detected' => $detected,
            'level' => $level,
            'reasons' => $this->normalizeStringList($value['reasons'] ?? []),
            'actions' => $this->normalizeStringList($value['actions'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonFromText(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) !== 1) {
            return null;
        }
        $decoded = json_decode((string) $matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }
}
