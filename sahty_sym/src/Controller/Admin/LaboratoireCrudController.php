<?php

namespace App\Controller\Admin;

use App\Entity\Laboratoire;
use App\Form\LaboratoireTypeAnalyseType;
use App\Repository\LaboratoireRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;

class LaboratoireCrudController extends AbstractCrudController
{
    public function __construct(private LaboratoireRepository $laboratoireRepository)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Laboratoire::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['nom'])
            ->overrideTemplates([
                'crud/index' => 'admin/laboratoire/index.html.twig',
            ]);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        if (Crud::PAGE_INDEX !== $responseParameters->get('pageName')) {
            return $responseParameters;
        }

        $total = $this->laboratoireRepository->count([]);
        $disponibles = $this->laboratoireRepository->count(['disponible' => true]);
        $indisponibles = $this->laboratoireRepository->count(['disponible' => false]);

        $topVilles = $this->laboratoireRepository->createQueryBuilder('l')
            ->select('l.ville AS ville, COUNT(l.id) AS total')
            ->groupBy('l.ville')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        $responseParameters->set('lab_stats', [
            'total' => $total,
            'disponibles' => $disponibles,
            'indisponibles' => $indisponibles,
            'top_villes' => $topVilles,
        ]);

        return $responseParameters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom')->setFormTypeOptions([
                'attr' => ['pattern' => "^[A-Za-z]+(?:[ '\\-][A-Za-z]+)*$", 'title' => 'Lettres, espaces, tirets, apostrophes.'],
            ])->setHelp('Lettres uniquement, espaces, tirets ou apostrophes.'),
            TextField::new('ville')->setFormTypeOptions([
                'attr' => ['pattern' => "^[A-Za-z]+(?:[ '\\-,][A-Za-z]+)*$", 'title' => 'Lettres, espaces, tirets, apostrophes, virgules.'],
            ])->setHelp('Lettres uniquement, espaces, tirets, apostrophes ou virgules.'),
            TextField::new('adresse')->setFormTypeOptions([
                'attr' => ['pattern' => "^[A-Za-z0-9 '\\-,]+$", 'title' => 'Lettres ou chiffres, espaces, tirets, apostrophes, virgules.'],
            ])->setHelp('Lettres ou chiffres, espaces, tirets, apostrophes ou virgules.'),
            TextField::new('telephone')->setFormTypeOptions([
                'attr' => ['inputmode' => 'numeric', 'pattern' => '^(?:\\+216\\s?)?[0-9]{8}$', 'title' => '8 chiffres, avec +216 optionnel.'],
            ])->setHelp('8 chiffres (ex: 20120555) ou +216 20120555.'),
            EmailField::new('email')->setRequired(false),
            TextField::new('numeroAgrement')->setRequired(false),
            NumberField::new('latitude')->setNumDecimals(6)->setHelp('Nombre entre -90 et 90.'),
            NumberField::new('longitude')->setNumDecimals(6)->setHelp('Nombre entre -180 et 180.'),
            BooleanField::new('disponible'),
            TextEditorField::new('description')->setRequired(false),
            CollectionField::new('laboratoireTypeAnalyses')
                ->setEntryType(LaboratoireTypeAnalyseType::class)
                ->setFormTypeOptions(['by_reference' => false])
                ->setEntryIsComplex(true)
                ->onlyOnForms(),
            DateTimeField::new('cree_le')->hideOnForm(),
        ];
    }
}
