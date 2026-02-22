<?php
// src/Form/ProduitType.php

namespace App\Form;

use App\Entity\Produit;
use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{
    TextType,
    TextareaType,
    NumberType,
    FileType,
    CheckboxType,
    ChoiceType,
    IntegerType
};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Crème hydratante'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire'])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Description détaillée du produit...'
                ],
                'required' => false
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (€)',
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est obligatoire']),
                    new Positive(['message' => 'Le prix doit être positif'])
                ]
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Quantité en stock',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'required' => false
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Soins du visage' => 'soins_visage',
                    'Soins du corps' => 'soins_corps',
                    'Soins capillaires' => 'soins_capillaires',
                    'Hygiène' => 'hygiene',
                    'Bien-être' => 'bien_etre',
                    'Nutrition' => 'nutrition',
                    'Minceur' => 'minceur',
                    'Bébé' => 'bebe',
                    'Homéopathie' => 'homeopathie',
                    'Compléments alimentaires' => 'complements',
                    'Matériel médical' => 'materiel',
                    'Autre' => 'autre'
                ],
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Sélectionnez une catégorie'
            ])
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Nivea, L\'Oréal, ...'
                ],
                'required' => false
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du produit',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WebP)',
                    ])
                ]
            ])
            ->add('promotion', NumberType::class, [
                'label' => 'Réduction (%)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 100,
                    'step' => 1
                ],
                'required' => false
            ])
            ->add('estActif', CheckboxType::class, [
                'label' => 'Produit actif (visible dans la boutique)',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('poids', NumberType::class, [
                'label' => 'Poids (g)',
                'attr' => [
                    'class' => 'form-control',
                    'step' => '1'
                ],
                'required' => false
            ])
            ->add('codeBarre', TextType::class, [
                'label' => 'Code-barres',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Optionnel'
                ],
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}