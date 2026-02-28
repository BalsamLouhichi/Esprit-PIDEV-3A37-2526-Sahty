<?php

namespace App\Form;

use App\Entity\Quiz;
use App\Entity\Recommandation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RecommandationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la recommandation',
                'attr'  => ['placeholder' => 'Ex : Recommandation pour anxiété modérée'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 150,
                        'minMessage' => 'Le nom doit contenir au moins 3 caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser 150 caractères.',
                    ]),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre court',
                'attr'  => ['placeholder' => 'Ex : Anxiété modérée - Voie 1'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le titre est obligatoire.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 255,
                        'minMessage' => 'Le titre doit contenir au moins 3 caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser 255 caractères.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description / Conseils',
                'required' => false,
                'attr'     => ['rows' => 5, 'placeholder' => 'Décrivez la recommandation...'],
            ])
            ->add('tips', TextareaType::class, [
                'label'    => 'Conseils pratiques',
                'required' => false,
                'attr'     => ['rows' => 3, 'placeholder' => '• Conseil 1' . "\n" . '• Conseil 2' . "\n" . '• Conseil 3'],
            ])
            ->add('min_score', IntegerType::class, [
                'label' => 'Score minimum',
                'attr'  => ['min' => 0],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le score minimum est obligatoire.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Le score minimum doit être >= 0.',
                    ]),
                ],
            ])
            ->add('max_score', IntegerType::class, [
                'label' => 'Score maximum',
                'attr'  => ['min' => 0],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le score maximum est obligatoire.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Le score maximum doit être >= 0.',
                    ]),
                ],
            ])
            ->add('target_categories', TextType::class, [
                'label'    => 'Catégories concernées',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : stress,concentration,sommeil'],
            ])
            ->add('video_url', TextType::class, [
                'label' => 'URL de la vidéo YouTube (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'https://www.youtube.com/watch?v=...'],
            ])
            ->add('severity', ChoiceType::class, [
                'label'   => 'Niveau de sévérité',
                'choices' => [
                    'Faible'  => 'low',
                    'Moyen'   => 'medium',
                    'Élevé'   => 'high',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('quiz', EntityType::class, [
                'class'         => Quiz::class,
                'choice_label'  => 'name',
                'label'         => 'Quiz associé',
                'placeholder'   => '— Choisir un quiz —',
                'required'      => true,
                'attr'          => ['class' => 'form-select', 'id' => 'recommandation_quiz'],
            ])
            ->add('type_probleme', ChoiceType::class, [
                'label'       => 'Question / Problème concerné',
                'choices'     => [],
                'placeholder' => '— Choisissez d’abord un quiz —',
                'required'    => true,

                'attr'        => ['class' => 'form-select', 'id' => 'recommandation_type_probleme'],
            ])
        ;

        // Listener pour recharger les choix quand le quiz change
        $formModifier = function (FormInterface $form, ?Quiz $quiz = null) {
            $choices = [];

            if ($quiz) {
                $questions = $quiz->getQuestions() ?? [];
                foreach ($questions as $question) {
                    $text = $question->getText() ?? "Question sans texte";
                    $id = $question->getId() ?? 0;
                    $choices[$text . ' (ID: ' . $id . ')'] = $text;  // ← Stocke le texte comme valeur
                }
            }

            $form->add('type_probleme', ChoiceType::class, [
                'label'       => 'Question / Problème concerné',
                'choices'     => $choices,
                'placeholder' => $quiz ? '— Choisir une question —' : 'Choisissez d’abord un quiz',
                'required'    => true,
                'attr'        => ['class' => 'form-select'],
                'disabled'    => empty($choices),
            ]);
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier) {
                $recommandation = $event->getData();
                $formModifier($event->getForm(), $recommandation?->getQuiz());
            }
        );

        $builder->get('quiz')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifier) {
                $quiz = $event->getForm()->getData();
                $formModifier($event->getForm()->getParent(), $quiz);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Recommandation::class,
        ]);
    }
}