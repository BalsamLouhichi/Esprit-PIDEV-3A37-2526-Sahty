<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\Produit;
use App\Entity\Parapharmacie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{
    IntegerType,
    TextType,
    EmailType,
    TextareaType,
    SubmitType
};
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $produit = $options['produit'];
        $parapharmacies = $options['parapharmacies'];

        $builder
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'data' => 1,
                'attr' => [
                    'min' => 1,
                    'max' => 99,
                    'class' => 'form-control'
                ]
            ])
            ->add('parapharmacie', EntityType::class, [
                'label' => 'Parapharmacie',
                'class' => Parapharmacie::class,
                'choices' => $parapharmacies,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez une parapharmacie',
                'attr' => ['class' => 'form-control']
            ])
            ->add('nomClient', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control']
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'attr' => ['class' => 'form-control']
            ])
            ->add('adresseLivraison', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes supplémentaires',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Instructions spéciales, allergies, préférences...'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Confirmer la commande',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg w-100'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'produit' => null,
            'parapharmacies' => [],
        ]);

        $resolver->setAllowedTypes('produit', Produit::class);
        $resolver->setAllowedTypes('parapharmacies', ['array', \Doctrine\Common\Collections\Collection::class, \Doctrine\ORM\PersistentCollection::class]);
    }
}
