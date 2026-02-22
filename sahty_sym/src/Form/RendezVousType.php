<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\Medecin;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('medecin', EntityType::class, [
                'class' => Medecin::class,
                'choice_label' => function (Medecin $medecin) {
                    return 'Dr. ' . $medecin->getNom() . ' ' .
                           $medecin->getPrenom() . ' - ' .
                           $medecin->getSpecialite();
                },
                'placeholder' => 'Sélectionnez un médecin',
                'label' => 'Médecin',
                'attr' => ['class' => 'form-control'],
            ])

            ->add('dateRdv', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date du rendez-vous',
                'attr' => ['class' => 'form-control'],
            ])

            ->add('heureRdv', TimeType::class, [
                'widget' => 'single_text',
                'label' => 'Heure du rendez-vous',
                'attr' => ['class' => 'form-control'],
            ])

            ->add('raison', TextareaType::class, [
                'label' => 'Motif de la consultation',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}