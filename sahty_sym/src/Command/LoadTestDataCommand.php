<?php

namespace App\Command;

use App\Entity\Quiz;
use App\Entity\Question;
use App\Entity\Recommandation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:load-test-data',
    description: 'Charge des données de test dans la base de données'
)]
class LoadTestDataCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier si les données existent déjà
        $quizCount = $this->em->getRepository(Quiz::class)->count([]);
        if ($quizCount > 0) {
            $io->warning('Des données de test existent déjà. Ignoré.');
            return Command::SUCCESS;
        }

        $io->title('Chargement des données de test...');

        // QUIZ 1: STRESS & ANXIÉTÉ
        $quiz1 = new Quiz();
        $quiz1->setName('Évaluation du Stress et de l\'Anxiété (GAD-7)');
        $quiz1->setDescription('Évaluez votre niveau d\'anxiété sur les 2 dernières semaines. Ce questionnaire est basé sur l\'échelle GAD-7 validée médicalement.');

        $questions1 = [
            'Au cours des 2 dernières semaines, à quelle fréquence vous avez senti de la nervosité ou de la tension ?',
            'À quelle fréquence avez-vous eu du mal à arrêter de vous inquiéter ou contrôler vos inquiétudes ?',
            'À quelle fréquence vous vous êtes inquiété pour trop de choses différentes ?',
            'À quelle fréquence avez-vous eu du mal à vous détendre ?',
            'À quelle fréquence vous vous êtes senti si agité que c\'était difficile de rester immobile ?',
            'À quelle fréquence vous vous êtes senti facilement irritable ou énervé ?',
            'À quelle fréquence avez-vous ressenti de la peur, comme si quelque chose de terrible allait se passer ?',
            'Durant la pandémie, avez-vous senti une amélioration de votre bien-être par rapport à avant ?',
        ];

        foreach ($questions1 as $idx => $text) {
            $q = new Question();
            $q->setText($text);
            $q->setType('likert_0_4');
            $q->setCategory($idx < 3 ? 'anxiete' : ($idx < 5 ? 'stress' : 'humeur'));
            $q->setOrderInQuiz($idx + 1);
            $q->setReverse($idx === 7);
            $q->setQuiz($quiz1);
            $this->em->persist($q);
        }

        // Recommandations pour Quiz 1
        $reco1_1 = new Recommandation();
        $reco1_1->setQuiz($quiz1);
        $reco1_1->setName('Techniques de relaxation');
        $reco1_1->setTitle('Pratiquez des exercices de respiration');
        $reco1_1->setDescription('Des exercices de respiration simples peuvent réduire l\'anxiété immédiatement.');
        $reco1_1->setTips('• 4-7-8 breathing: Inspirez 4s, retenez 7s, expirez 8s\n• Box breathing: 4-4-4-4\n• Pratiquez 5 min par jour');
        $reco1_1->setMinScore(0);
        $reco1_1->setMaxScore(16);
        $reco1_1->setTypeProbleme('anxiety_low');
        $reco1_1->setTargetCategories('anxiete,stress');
        $reco1_1->setSeverity('low');
        $this->em->persist($reco1_1);

        $reco1_2 = new Recommandation();
        $reco1_2->setQuiz($quiz1);
        $reco1_2->setName('Gestion du stress modéré');
        $reco1_2->setTitle('Consultez un professionnel');
        $reco1_2->setDescription('Votre niveau de stress nécessite une prise en charge professionnelle.');
        $reco1_2->setTips('• Consultez un psychologue ou psychiatre\n• Thérapie cognitivo-comportementale (TCC)\n• Méditation en pleine conscience\n• Activités physiques régulières');
        $reco1_2->setMinScore(17);
        $reco1_2->setMaxScore(24);
        $reco1_2->setTypeProbleme('anxiety_medium');
        $reco1_2->setTargetCategories('anxiete,stress');
        $reco1_2->setSeverity('medium');
        $this->em->persist($reco1_2);

        $reco1_3 = new Recommandation();
        $reco1_3->setQuiz($quiz1);
        $reco1_3->setName('Appel à l\'aide immédiat');
        $reco1_3->setTitle('Contactez un professionnel urgently');
        $reco1_3->setDescription('Votre anxiété est sévère et nécessite une prise en charge urgente.');
        $reco1_3->setTips('• Contactez votre médecin immédiatement\n• Appelez le service d\'urgence psychiatrique\n• Consultez les ressources d\'aide de crise\n• Numéro d\'urgence: 15 (SAMU)');
        $reco1_3->setMinScore(25);
        $reco1_3->setMaxScore(32);
        $reco1_3->setTypeProbleme('anxiety_high');
        $reco1_3->setTargetCategories('anxiete,stress');
        $reco1_3->setSeverity('high');
        $this->em->persist($reco1_3);

        // QUIZ 2: DÉPRESSION
        $quiz2 = new Quiz();
        $quiz2->setName('Échelle de Dépression (PHQ-9)');
        $quiz2->setDescription('Évaluez votre niveau de dépression sur les 2 dernières semaines.');

        $questions2 = [
            'Intérêt ou plaisir diminué pour faire des choses',
            'Sentiment de dépression, de déprime ou de désespoir',
            'Problèmes avec le sommeil (trop ou pas assez)',
            'Sentiment de fatigue ou manque d\'énergie',
            'Changement marqué de votre appétit (plus ou moins)',
            'Sentiments d\'échec ou de culpabilité',
            'Difficultés de concentration',
            'Pensées que vous seriez mieux mort ou pensées d\'auto-harm',
            'Difficult à fonctionner au travail, à la maison ou socially',
        ];

        foreach ($questions2 as $idx => $text) {
            $q = new Question();
            $q->setText($text);
            $q->setType('likert_0_4');
            $q->setCategory('humeur');
            $q->setOrderInQuiz($idx + 1);
            $q->setReverse(false);
            $q->setQuiz($quiz2);
            $this->em->persist($q);
        }

        // Recommandations pour Quiz 2
        $reco2_1 = new Recommandation();
        $reco2_1->setQuiz($quiz2);
        $reco2_1->setName('Maintenir une bonne hygiène de vie');
        $reco2_1->setTitle('Prenez soin de votre bien-être');
        $reco2_1->setDescription('Votre score indique une dépression légère.');
        $reco2_1->setTips('• Dormez régulièrement (7-9h par nuit)\n• Faites de l\'exercice physique\n• Mangez sainement\n• Passez du temps en nature');
        $reco2_1->setMinScore(0);
        $reco2_1->setMaxScore(10);
        $reco2_1->setTypeProbleme('depression_light');
        $reco2_1->setTargetCategories('humeur');
        $reco2_1->setSeverity('low');
        $this->em->persist($reco2_1);

        $this->em->flush();

        $io->success(sprintf(
            'Données de test chargées: %d quiz, %d questions, %d recommandations',
            2,
            count($questions1) + count($questions2),
            3
        ));

        return Command::SUCCESS;
    }
}
