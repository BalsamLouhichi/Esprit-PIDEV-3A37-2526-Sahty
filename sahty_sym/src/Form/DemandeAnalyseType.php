<?php
// src/Form/DemandeAnalyseType.php

namespace App\Form;

use App\Entity\DemandeAnalyse;
use App\Entity\Patient;
use App\Entity\Medecin;
use App\Entity\Laboratoire;
use App\Repository\TypeAnalyseRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeAnalyseType extends AbstractType
{
    private TypeAnalyseRepository $typeAnalyseRepository;

    public function __construct(TypeAnalyseRepository $typeAnalyseRepository)
    {
        $this->typeAnalyseRepository = $typeAnalyseRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $userRole = $options['user_role'];
        $userEntity = $options['user_entity'];
        $laboratoire = $options['laboratoire'] ?? null;

        $typeAnalyseChoices = [];
        $typeAnalyses = $this->typeAnalyseRepository->findBy(['actif' => true], ['categorie' => 'ASC', 'nom' => 'ASC']);
        foreach ($typeAnalyses as $typeAnalyse) {
            $categorie = $typeAnalyse->getCategorie() ?: 'Autres';
            $nom = $typeAnalyse->getNom() ?: 'Analyse';
            if (!isset($typeAnalyseChoices[$categorie])) {
                $typeAnalyseChoices[$categorie] = [];
            }
            $typeAnalyseChoices[$categorie][$nom] = $nom;
        }

        $builder
            ->add('type_bilan', ChoiceType::class, [
                'label' => 'Type de bilan',
                'choices' => $typeAnalyseChoices,
                'required' => true,
                'placeholder' => 'Sélectionnez un type de bilan',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes complémentaires',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Informations complémentaires, symptômes, etc.'
                ]
            ])
            ->add('priorite', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Normale' => 'normale',
                    'Haute' => 'haute',
                    'Urgent' => 'urgent'
                ],
                'required' => true,
            ]);

        // Si ce n'est pas un patient, on affiche le champ patient
        if ($userRole !== 'ROLE_PATIENT') {
            $builder->add('patient', EntityType::class, [
                'label' => 'Patient',
                'class' => Patient::class,
                'choice_label' => function(Patient $patient) {
                    return $patient->getNomComplet() . ' (' . $patient->getEmail() . ')';
                },
                'required' => true,
                'placeholder' => 'Sélectionnez un patient',
            ]);
        }

        // Champ médecin - TOUJOURS optionnel pour tous les rôles
        $builder->add('medecin', EntityType::class, [
            'label' => 'Médecin prescripteur (optionnel)',
            'class' => Medecin::class,
            'choice_label' => function(Medecin $medecin) {
                return 'Dr. ' . $medecin->getNomComplet() . ($medecin->getSpecialite() ? ' - ' . $medecin->getSpecialite() : '');
            },
            'required' => false,
            'placeholder' => 'Aucun médecin (facultatif)',
            'empty_data' => null,
            'help' => 'Laissez vide si vous n\'avez pas de médecin référent',
        ]);

        // Champ laboratoire
        $laboratoireFieldOptions = [
            'label' => 'Laboratoire',
            'class' => Laboratoire::class,
            'choice_label' => function(Laboratoire $laboratoire) {
                return $laboratoire->getNom() . ' - ' . $laboratoire->getVille();
            },
            'required' => true,
            'placeholder' => 'Sélectionnez un laboratoire',
        ];
        if ($laboratoire) {
            $laboratoireFieldOptions['data'] = $laboratoire; // Pré-rempli si laboratoire spécifié
        }
        $builder->add('laboratoire', EntityType::class, $laboratoireFieldOptions);

        // Date programmée (optionnelle)
        $builder->add('programme_le', DateTimeType::class, [
            'label' => 'Date et heure programmée (optionnel)',
            'required' => false,
            'widget' => 'single_text',
            'html5' => true,
            'attr' => [
                'class' => 'datetime-picker'
            ]
        ]);

        // Le statut est determine automatiquement a partir du PDF resultat

        $builder->get('priorite')->addModelTransformer(new CallbackTransformer(
            static fn ($value) => $value ? strtolower((string) $value) : $value,
            static fn ($value) => $value ? strtolower((string) $value) : $value
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeAnalyse::class,
            'user_role' => null,
            'user_entity' => null,
            'laboratoire' => null,
        ]);
    }
}