<?php

namespace App\Form;

use App\Entity\Question;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextareaType::class, [
                'label' => 'Texte de la question',
                'attr' => ['rows' => 3, 'placeholder' => 'Ex: Au cours des 2 dernières semaines, à quelle fréquence...'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le texte de la question est obligatoire.']),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 1000,
                        'minMessage' => 'La question doit contenir au moins 5 caractères.',
                        'maxMessage' => 'La question ne peut pas dépasser 1000 caractères.',
                    ]),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de réponse',
                'choices' => [
                    'Échelle 0-4 (Jamais → Très souvent)' => 'likert_0_4',
                    'Échelle 1-5' => 'likert_1_5',
                    'Oui / Non' => 'yes_no',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le type de réponse est obligatoire.']),
                ],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Stress' => 'stress',
                    'Anxiété' => 'anxiete',
                    'Concentration' => 'concentration',
                    'Sommeil' => 'sommeil',
                    'Humeur' => 'humeur',
                ],
                'placeholder' => 'Choisir une catégorie',
                'required' => false,
            ])
            ->add('orderInQuiz', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => ['min' => 1],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'ordre est obligatoire.']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'L\'ordre doit être >= 1.',
                    ]),
                ],
            ])
            ->add('reverse', CheckboxType::class, [
                'label' => 'Question inversée (reverse scoring)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
        ]);
    }
}