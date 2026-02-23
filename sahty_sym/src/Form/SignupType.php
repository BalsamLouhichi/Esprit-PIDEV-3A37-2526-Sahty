<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SignupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'votre@email.com']
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe *',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 6]),
                ],
                'attr' => ['class' => 'form-control', 'placeholder' => '••••••••']
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre nom']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom *',
                'required' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre prénom']
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '06 12 34 56 78']
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre ville']
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle *',
                'required' => true,
                'choices' => [
                    'Patient' => 'patient',
                    'Médecin' => 'medecin',
                    'Responsable Laboratoire' => 'responsable_labo',
                    'Responsable Parapharmacie' => 'responsable_para',
                    // 'Administrateur' => 'admin', // À décommenter si besoin
                ],
                'placeholder' => 'Choisissez votre rôle',
                'attr' => ['class' => 'form-control']
            ])
            ->add('photoProfil', FileType::class, [
                'label' => 'Photo de profil',
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // Pas de data_class car on gère plusieurs entités
        ]);
    }
}
