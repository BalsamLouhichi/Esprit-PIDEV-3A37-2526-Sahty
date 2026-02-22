<?php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\ResponsableLaboratoire;
use App\Entity\ResponsableParapharmacie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre@email.com'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'email est obligatoire']),
                    new Assert\Email(['message' => 'Veuillez entrer un email valide']),
                    new Assert\Length([
                        'max' => 180,
                        'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
                        'message' => 'Le nom ne peut contenir que des lettres, espaces et tirets'
                    ])
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom *',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
                        'message' => 'Le prénom ne peut contenir que des lettres, espaces et tirets'
                    ])
                ]
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78'
                ],
                'constraints' => [
                    new Assert\Length([
                        'min' => 8,
                        'max' => 20,
                        'minMessage' => 'Le téléphone doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le téléphone ne peut pas dépasser {{ limit }} caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[0-9\s\+\-\(\)\.]+$/',
                        'message' => 'Le téléphone ne peut contenir que des chiffres, espaces, +, -, ( et )'
                    ])
                ]
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\LessThan([
                        'value' => '-18 years',
                        'message' => 'Vous devez avoir au moins 18 ans'
                    ])
                ]
            ])
            ->add('photoProfil', FileType::class, [
                'label' => 'Photo de profil',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize' => '2048k', // Ajouté: limite à 2MB
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF ou WebP)',
                        'maxSizeMessage' => 'L\'image est trop volumineuse ({{ size }} {{ suffix }}). Maximum autorisé: {{ limit }} {{ suffix }}' // Ajouté
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ]);

        // Ajouter des champs spécifiques selon le type d'utilisateur
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $user = $event->getData();
            $form = $event->getForm();

            if ($user instanceof Medecin) {
                $form->add('specialite', TextType::class, [
                    'label' => 'Spécialité',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Cardiologie, Dermatologie, etc.'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'max' => 100,
                            'maxMessage' => 'La spécialité ne peut pas dépasser {{ limit }} caractères'
                        ])
                    ]
                ])
                ->add('anneeExperience', IntegerType::class, [
                    'label' => 'Années d\'expérience',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '5'
                    ],
                    'constraints' => [
                        new Assert\Range([
                            'min' => 0,
                            'max' => 60,
                            'notInRangeMessage' => 'Les années d\'expérience doivent être entre {{ min }} et {{ max }}' // CORRIGÉ
                        ])
                    ]
                ])
                ->add('grade', TextType::class, [
                    'label' => 'Grade',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Docteur, Professeur, etc.'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'max' => 50,
                            'maxMessage' => 'Le grade ne peut pas dépasser {{ limit }} caractères'
                        ])
                    ]
                ])
                ->add('adresseCabinet', TextType::class, [
                    'label' => 'Adresse du cabinet',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '123 Rue du Cabinet'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
                        ])
                    ]
                ])
                ->add('telephoneCabinet', TextType::class, [
                    'label' => 'Téléphone du cabinet',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '01 23 45 67 89'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'min' => 8,
                            'max' => 20,
                            'minMessage' => 'Le téléphone doit contenir au moins {{ limit }} caractères',
                            'maxMessage' => 'Le téléphone ne peut pas dépasser {{ limit }} caractères'
                        ]),
                        new Assert\Regex([
                            'pattern' => '/^[0-9\s\+\-\(\)\.]+$/',
                            'message' => 'Le téléphone ne peut contenir que des chiffres, espaces, +, -, ( et )'
                        ])
                    ]
                ])
                ->add('nomEtablissement', TextType::class, [
                    'label' => 'Nom de l\'établissement',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Hôpital Saint-Louis'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'max' => 150,
                            'maxMessage' => 'Le nom de l\'établissement ne peut pas dépasser {{ limit }} caractères'
                        ])
                    ]
                ])
                ->add('numeroUrgence', TextType::class, [
                    'label' => 'Numéro d\'urgence',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Numéro d\'urgence'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'min' => 8,
                            'max' => 20,
                            'minMessage' => 'Le numéro d\'urgence doit contenir au moins {{ limit }} caractères',
                            'maxMessage' => 'Le numéro d\'urgence ne peut pas dépasser {{ limit }} caractères'
                        ]),
                        new Assert\Regex([
                            'pattern' => '/^[0-9\s\+\-\(\)\.]+$/',
                            'message' => 'Le numéro d\'urgence ne peut contenir que des chiffres, espaces, +, -, ( et )'
                        ])
                    ]
                ]);
            } elseif ($user instanceof Patient) {
                $form->add('groupeSanguin', ChoiceType::class, [
                    'label' => 'Groupe sanguin',
                    'required' => false,
                    'choices' => [
                        'A+' => 'A+',
                        'A-' => 'A-',
                        'B+' => 'B+',
                        'B-' => 'B-',
                        'AB+' => 'AB+',
                        'AB-' => 'AB-',
                        'O+' => 'O+',
                        'O-' => 'O-'
                    ],
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ])
                ->add('contactUrgence', TextType::class, [
                    'label' => 'Contact d\'urgence',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '06 12 34 56 78'
                    ],
                    'constraints' => [
                        new Assert\Length([
                            'min' => 8,
                            'max' => 20,
                            'minMessage' => 'Le contact d\'urgence doit contenir au moins {{ limit }} caractères',
                            'maxMessage' => 'Le contact d\'urgence ne peut pas dépasser {{ limit }} caractères'
                        ]),
                        new Assert\Regex([
                            'pattern' => '/^[0-9\s\+\-\(\)\.]+$/',
                            'message' => 'Le contact d\'urgence ne peut contenir que des chiffres, espaces, +, -, ( et )'
                        ])
                    ]
                ])
                ->add('sexe', ChoiceType::class, [
                    'label' => 'Sexe',
                    'required' => false,
                    'choices' => [
                        'Masculin' => 'M',
                        'Féminin' => 'F',
                        'Autre' => 'A'
                    ],
                    'attr' => [
                        'class' => 'form-control'
                    ]
                ]);
            } elseif ($user instanceof ResponsableLaboratoire) {
                $form->add('laboratoireId', IntegerType::class, [
                    'label' => 'ID du laboratoire',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '123'
                    ],
                    'constraints' => [
                        new Assert\Positive([
                            'message' => 'L\'ID du laboratoire doit être un nombre positif'
                        ]),
                        new Assert\Range([
                            'min' => 1,
                            'max' => 999999,
                            'notInRangeMessage' => 'L\'ID du laboratoire doit être entre {{ min }} et {{ max }}' // CORRIGÉ
                        ])
                    ]
                ]);
            } elseif ($user instanceof ResponsableParapharmacie) {
                $form->add('parapharmacieId', IntegerType::class, [
                    'label' => 'ID de la parapharmacie',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => '456'
                    ],
                    'constraints' => [
                        new Assert\Positive([
                            'message' => 'L\'ID de la parapharmacie doit être un nombre positif'
                        ]),
                        new Assert\Range([
                            'min' => 1,
                            'max' => 999999,
                            'notInRangeMessage' => 'L\'ID de la parapharmacie doit être entre {{ min }} et {{ max }}' // CORRIGÉ
                        ])
                    ]
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}