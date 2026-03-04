<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\GroupeCible;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin   = $options['is_admin'] ?? false;
        $userRole  = $options['user_role'] ?? null;
        $isDemande = $options['is_demande'] ?? false;
        $seriesCandidates = $options['series_candidates'] ?? [];
        $seriesEditionNumber = $options['series_edition_number'] ?? null;

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'ÃƒÂ©vÃƒÂ©nement',
                'attr' => ['placeholder' => 'Ex: Webinaire sur la nutrition'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description dÃƒÂ©taillÃƒÂ©e',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'ÃƒÂ©vÃƒÂ©nement',
                'choices' => [
                    'Webinaire' => 'webinaire',
                    'Atelier' => 'atelier',
                    'DÃƒÂ©pistage' => 'depistage',
                    'ConfÃƒÂ©rence' => 'conference',
                    'Groupe de parole' => 'groupe_parole',
                    'Formation' => 'formation',
                ],
                'placeholder' => 'Choisir un type...',
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'Mode de participation',
                'choices' => [
                    'En ligne' => 'en_ligne',
                    'PrÃƒÂ©sentiel' => 'presentiel',
                    'Hybride' => 'hybride',
                ],
            ])
            ->add('meetingPlatform', ChoiceType::class, [
                'label' => 'Plateforme de reunion',
                'required' => false,
                'placeholder' => 'Selectionner...',
                'choices' => [
                    'Jitsi (auto)' => 'jitsi',
                    'Lien personnalise' => 'custom',
                ],
            ])
            ->add('meetingLink', TextType::class, [
                'label' => 'Lien de reunion',
                'required' => false,
            ])
            ->add('isEdition', CheckboxType::class, [
                'label' => 'Cet evenement est une edition d une serie',
                'required' => false,
                'mapped' => false,
                'data' => ($seriesEditionNumber !== null && (int) $seriesEditionNumber > 0),
            ])
            ->add('editionSourceEventId', ChoiceType::class, [
                'label' => 'Evenement source',
                'required' => false,
                'mapped' => false,
                'choices' => is_array($seriesCandidates) ? $seriesCandidates : [],
                'placeholder' => 'Selectionner un evenement',
            ])
            ->add('editionNumero', IntegerType::class, [
                'label' => 'Numero d edition',
                'required' => false,
                'mapped' => false,
                'data' => is_numeric($seriesEditionNumber) ? (int) $seriesEditionNumber : null,
                'attr' => ['min' => 1, 'step' => 1],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de dÃƒÂ©but',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'input' => 'datetime',
                'model_timezone' => 'Africa/Tunis',
                'view_timezone' => 'Africa/Tunis',
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'input' => 'datetime',
                'model_timezone' => 'Africa/Tunis',
                'view_timezone' => 'Africa/Tunis',
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu / Lien',
                'required' => false,
                'help' => 'Adresse physique ou lien de rÃƒÂ©union',
            ])
            ->add('placesMax', IntegerType::class, [
                'label' => 'Nombre de places maximum',
                'required' => false,
            ])
            ->add('tarif', MoneyType::class, [
                'label' => 'Tarif (TND)',
                'currency' => 'TND',
                'required' => false,
                'scale' => 2,
            ])
            ->add('groupeCibles', EntityType::class, [
                'label' => 'Groupes cibles',
                'class' => GroupeCible::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => function (EntityRepository $er) use ($userRole, $isAdmin) {
                    $qb = $er->createQueryBuilder('g')
                        ->orderBy('g.nom', 'ASC');

                    if ($isAdmin) {
                        return $qb;
                    }

                    // Defensive check: Ensure we handle roles safely
                    if ($userRole) {
                        if ($userRole === 'ROLE_PATIENT') {
                            $qb->andWhere('g.type LIKE :type')->setParameter('type', '%patient%');
                        } elseif ($userRole === 'ROLE_MEDECIN') {
                            $qb->andWhere('g.type LIKE :type')->setParameter('type', '%medecin%');
                        } elseif ($userRole === 'ROLE_RESPONSABLE_LABO') {
                            $qb->andWhere('g.type LIKE :type')->setParameter('type', '%laboratoire%');
                        } elseif ($userRole === 'ROLE_RESPONSABLE_PARA') {
                            $qb->andWhere('g.type LIKE :type')->setParameter('type', '%paramedical%');
                        }
                    }

                    return $qb;
                },
            ]);

        // LOGIC: Only add the 'statut' field if this is NOT a client request (is_demande = false).
        // If it IS a client request, we skip this entirely so the form doesn't touch the status.
        if (!$isDemande) {
            $statutChoices = [
                'PlanifiÃƒÂ©' => 'planifie',
                'En cours' => 'en_cours',
                'TerminÃƒÂ©' => 'termine',
                'AnnulÃƒÂ©' => 'annule',
            ];

            if ($isAdmin) {
                $statutChoices = array_merge($statutChoices, [
                    'En attente d\'approbation' => 'en_attente_approbation',
                    'ApprouvÃƒÂ©' => 'approuve',
                ]);
            }

            $builder->add('statut', ChoiceType::class, [
                'label' => 'Statut de l\'ÃƒÂ©vÃƒÂ©nement',
                'choices' => $statutChoices,
                'required' => true, 
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
            'is_edit' => false,
            'is_admin' => false,
            'is_demande' => false,
            'user_role' => null,
            'series_candidates' => [],
            'series_edition_number' => null,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('is_admin', 'bool');
        $resolver->setAllowedTypes('is_demande', 'bool');
        $resolver->setAllowedTypes('user_role', ['null', 'string']);
        $resolver->setAllowedTypes('series_candidates', 'array');
        $resolver->setAllowedTypes('series_edition_number', ['null', 'int', 'string']);
    }
}