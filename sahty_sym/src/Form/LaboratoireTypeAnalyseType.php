<?php

namespace App\Form;

use App\Entity\LaboratoireTypeAnalyse;
use App\Entity\TypeAnalyse;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LaboratoireTypeAnalyseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeAnalyse', EntityType::class, [
                'class' => TypeAnalyse::class,
                'choice_label' => 'nom',
                'label' => 'Type d\'analyse',
                'required' => true,
            ])
            ->add('disponible', CheckboxType::class, [
                'label' => 'Disponible',
                'required' => false,
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (DT)',
                'currency' => 'TND',
                'required' => false,
                'scale' => 2,
            ])
            ->add('delaiResultatHeures', IntegerType::class, [
                'label' => 'Délai (heures)',
                'required' => false,
            ])
            ->add('conditions', TextareaType::class, [
                'label' => 'Conditions',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LaboratoireTypeAnalyse::class,
        ]);
    }
}