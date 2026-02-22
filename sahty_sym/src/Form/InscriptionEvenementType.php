<?php

namespace App\Form;

use App\Entity\InscriptionEvenement;
use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Validator\Constraints as Assert;

class InscriptionEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Evenement $evenement */
        $evenement = $options['evenement'];
        
        $builder
            ->add('userType', ChoiceType::class, [
                'label' => 'Type de participant',
                'choices' => [
                    'Patient' => 'patient',
                    'Médecin' => 'medecin',
                    'Pharmacien' => 'pharmacien',
                    'Laboratoire' => 'laboratoire',
                    'Visiteur / Étudiant' => 'visiteur',
                ],
                'expanded' => false,
                'multiple' => false,
                'placeholder' => 'Sélectionnez votre profil',
                'attr' => [
                    'class' => 'form-select',
                    'data-tarif-base' => $evenement->getTarif() ?? 25
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Veuillez sélectionner votre type de profil'
                    ])
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le nom est obligatoire'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le prénom est obligatoire'
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre@email.com'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'L\'email est obligatoire'
                    ]),
                    new Assert\Email([
                        'message' => 'Veuillez saisir un email valide'
                    ])
                ]
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre numéro de téléphone'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le téléphone est obligatoire'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[0-9\s\-\+\(\)]{10,20}$/',
                        'message' => 'Veuillez saisir un numéro de téléphone valide'
                    ])
                ]
            ])
            ->add('numeroOrdre', TextType::class, [
                'label' => 'Numéro RPPS/Ordre (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Pour les professionnels de santé'
                ]
            ])
            ->add('etablissement', TextType::class, [
                'label' => 'Établissement (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre établissement ou entreprise'
                ]
            ])
            ->add('montant', HiddenType::class)
            ->add('reference', HiddenType::class)
            ->add('specialite', TextType::class, [
                'label' => 'Spécialité (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre spécialité médicale'
                ]
            ])
            ->add('commentaires', TextType::class, [
                'label' => 'Commentaires (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Informations complémentaires'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InscriptionEvenement::class,
            'evenement' => null,
        ]);
        
        $resolver->setRequired('evenement');
    }
}