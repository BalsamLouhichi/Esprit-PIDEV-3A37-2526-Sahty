<?php

namespace App\Form;

use App\Entity\FicheMedicale;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FicheMedicaleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isMedecin = $options['is_medecin'];

        $builder
            // ============ SECTION PATIENT (Médecin peut tout modifier) ============
            ->add('antecedents', TextareaType::class, [
                'label' => '📋 Antécédents médicaux',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez les antécédents médicaux...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'Les antécédents ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('allergies', TextareaType::class, [
                'label' => '⚠️ Allergies',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Listez les allergies connues...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Les allergies ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('taille', NumberType::class, [
                'label' => '📏 Taille (en mètres)',
                'required' => false,
                'attr' => [
                    'step' => '0.01',
                    'min' => '0.50',
                    'max' => '2.50',
                    'placeholder' => 'Ex: 1.75',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Range([
                        'min' => 0.50,
                        'max' => 2.50,
                        'notInRangeMessage' => 'La taille doit être entre {{ min }}m et {{ max }}m.'
                    ])
                ]
            ])
            ->add('poids', NumberType::class, [
                'label' => '⚖️ Poids (en kg)',
                'required' => false,
                'attr' => [
                    'min' => '1',
                    'max' => '300',
                    'placeholder' => 'Ex: 70',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Range([
                        'min' => 1,
                        'max' => 300,
                        'notInRangeMessage' => 'Le poids doit être entre {{ min }}kg et {{ max }}kg.'
                    ])
                ]
            ])
            ->add('traitement_en_cours', TextareaType::class, [
                'label' => '💊 Traitements en cours',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Médicaments actuels...',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 1500,
                        'maxMessage' => 'Les traitements ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            
            // ============ SECTION MÉDECIN ============
            ->add('diagnostic', TextareaType::class, [
                'label' => '🩺 Diagnostic',
                'required' => $isMedecin, // Obligatoire pour le médecin
                'attr' => [
                    'rows' => 4,
                    'class' => 'form-control',
                    'placeholder' => 'Saisissez votre diagnostic...'
                ],
                'constraints' => $isMedecin ? [
                    new Assert\NotBlank([
                        'message' => 'Le diagnostic est obligatoire.'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 2000,
                        'minMessage' => 'Le diagnostic doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le diagnostic ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ] : []
            ])
            ->add('traitement_prescrit', TextareaType::class, [
                'label' => '💉 Traitement prescrit',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'class' => 'form-control',
                    'placeholder' => 'Médicaments, posologie, durée...'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'Le traitement prescrit ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('observations', TextareaType::class, [
                'label' => '📝 Observations médicales',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control',
                    'placeholder' => 'Notes complémentaires, recommandations...'
                ],
                'constraints' => [
                    new Assert\Length([
                        'max' => 3000,
                        'maxMessage' => 'Les observations ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => '📊 Statut',
                'choices' => [
                    'Actif' => 'actif',
                    'Modifié' => 'modifié',
                    'Inactif' => 'inactif'
                ],
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le statut est obligatoire.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FicheMedicale::class,
            'is_medecin' => false,
        ]);

        $resolver->setAllowedTypes('is_medecin', 'bool');
    }
}
