<?php

namespace App\Form;

use App\Entity\Laboratoire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LaboratoireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du laboratoire *',
                'required' => true,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Ex: Laboratoire Central Medinova'
                ]
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville *',
                'required' => true,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Ex: Tunis'
                ]
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse complète',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Ex: 123 Rue Médicale, Centre-ville',
                    'rows' => 2
                ]
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone principal *',
                'required' => true,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => '+216 XX XXX XXX'
                ]
            ])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Ex: 36.8065'
                ]
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Ex: 10.1815'
                ]
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Laboratoire disponible',
                'required' => false,
                'label_attr' => ['class' => 'form-check-label fw-bold'],
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Description courte du laboratoire...',
                    'rows' => 3
                ]
            ])
            ->add('laboratoireTypeAnalyses', CollectionType::class, [
                'entry_type' => LaboratoireTypeAnalyseType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'attr' => ['class' => 'type-analyse-collection'],
                'prototype' => true,
                'prototype_name' => '__type_analyse_prototype__',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Laboratoire::class,
        ]);
    }
}