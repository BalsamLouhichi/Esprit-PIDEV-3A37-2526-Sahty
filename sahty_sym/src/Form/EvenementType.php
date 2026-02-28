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
        $isAdmin = $options['is_admin'] ?? false;
        $userRole = $options['user_role'] ?? null;
        $isDemande = $options['is_demande'] ?? false;
        $aiSuggestedDate = $options['ai_suggested_date'] ?? null;
        $aiSuggestedLieu = $options['ai_suggested_lieu'] ?? null;
        $aiSuggestedParticipants = $options['ai_suggested_participants'] ?? null;
        $seriesCandidates = $options['series_candidates'] ?? [];
        $seriesIsEdition = (bool) ($options['series_is_edition'] ?? false);
        $seriesSourceEventId = $options['series_source_event_id'] ?? null;
        $seriesEditionNumber = $options['series_edition_number'] ?? null;

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'événement',
                'attr' => ['placeholder' => 'Ex: Webinaire sur la nutrition'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'événement',
                'choices' => [
                    'Webinaire' => 'webinaire',
                    'Atelier' => 'atelier',
                    'Dépistage' => 'depistage',
                    'Conférence' => 'conference',
                    'Groupe de parole' => 'groupe_parole',
                    'Formation' => 'formation',
                ],
                'placeholder' => 'Choisir un type...',
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'Mode de participation',
                'choices' => [
                    'En ligne' => 'en_ligne',
                    'Présentiel' => 'presentiel',
                    'Hybride' => 'hybride',
                ],
            ])
            ->add('meetingPlatform', ChoiceType::class, [
                'label' => 'Plateforme de réunion (en ligne)',
                'required' => false,
                'placeholder' => 'Génération automatique (Jitsi)',
                'choices' => [
                    'Jitsi (gratuit)' => 'jitsi',
                    'Lien personnalisé' => 'custom',
                ],
                'help' => 'Pour les événements en ligne/hybrides. Le lien est généré à l\'approbation si vous choisissez Jitsi.',
            ])
            ->add('meetingLink', TextType::class, [
                'label' => 'Lien personnalisé',
                'required' => false,
                'help' => 'Obligatoire seulement si plateforme = Lien personnalisé.',
                'attr' => [
                    'placeholder' => 'https://...',
                    'maxlength' => 500,
                ],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de début',
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
                'help' => 'Adresse physique ou lien de réunion',
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
            ->add('isEdition', CheckboxType::class, [
                'label' => 'Cet evenement est une edition d\'une serie existante',
                'mapped' => false,
                'required' => false,
                'data' => $seriesIsEdition,
            ])
            ->add('editionSourceEventId', ChoiceType::class, [
                'label' => 'Evenement de reference',
                'mapped' => false,
                'required' => false,
                'choices' => $seriesCandidates,
                'placeholder' => 'Selectionner un evenement precedent',
                'data' => $seriesSourceEventId !== null ? (string) $seriesSourceEventId : null,
            ])
            ->add('editionNumero', IntegerType::class, [
                'label' => 'Numero de l\'edition',
                'mapped' => false,
                'required' => false,
                'data' => $seriesEditionNumber,
                'attr' => [
                    'min' => 1,
                    'max' => 999,
                ],
            ])
            ->add('groupeCibles', EntityType::class, [
                'label' => 'Groupes cibles',
                'class' => GroupeCible::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => function (EntityRepository $er) use ($userRole, $isAdmin, $isDemande) {
                    $qb = $er->createQueryBuilder('g')
                        ->orderBy('g.nom', 'ASC');

                    if ($isAdmin || $isDemande) {
                        return $qb;
                    }

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

        if (!$isDemande) {
            $statutChoices = [
                'Planifié' => 'planifie',
                'En cours' => 'en_cours',
                'Terminé' => 'termine',
                'Annulé' => 'annule',
            ];

            if ($isAdmin) {
                $statutChoices = array_merge($statutChoices, [
                    'En attente d\'approbation' => 'en_attente_approbation',
                    'Approuvé' => 'approuve',
                ]);
            }

            $builder->add('statut', ChoiceType::class, [
                'label' => 'Statut de l\'événement',
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
            'series_is_edition' => false,
            'series_source_event_id' => null,
            'series_edition_number' => null,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('is_admin', 'bool');
        $resolver->setAllowedTypes('is_demande', 'bool');
        $resolver->setAllowedTypes('user_role', ['null', 'string']);
        $resolver->setAllowedTypes('series_candidates', 'array');
        $resolver->setAllowedTypes('series_is_edition', 'bool');
        $resolver->setAllowedTypes('series_source_event_id', ['null', 'int', 'string']);
        $resolver->setAllowedTypes('series_edition_number', ['null', 'int']);
    }
}
