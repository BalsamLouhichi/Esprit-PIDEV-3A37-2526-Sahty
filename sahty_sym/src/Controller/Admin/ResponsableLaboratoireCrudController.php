<?php

namespace App\Controller\Admin;

use App\Entity\ResponsableLaboratoire;
use App\Repository\ResponsableLaboratoireRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class ResponsableLaboratoireCrudController extends AbstractCrudController
{
    public function __construct(private ResponsableLaboratoireRepository $responsableRepository)
    {
    }

    public static function getEntityFqcn(): string
    {
        return ResponsableLaboratoire::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(['nom', 'prenom'])
            ->overrideTemplates([
                'crud/index' => 'admin/responsable_labo/index.html.twig',
            ]);
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addCssFile('css/admin-responsables.css');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);

        if (Crud::PAGE_INDEX !== $responseParameters->get('pageName')) {
            return $responseParameters;
        }

        $total = $this->responsableRepository->count([]);
        $actifs = $this->responsableRepository->count(['estActif' => true]);
        $inactifs = $this->responsableRepository->count(['estActif' => false]);

        $topVilles = $this->responsableRepository->createQueryBuilder('r')
            ->leftJoin('r.laboratoire', 'l')
            ->select('l.ville AS ville, COUNT(r.id) AS total')
            ->where('l.ville IS NOT NULL')
            ->groupBy('l.ville')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        $responseParameters->set('responsable_stats', [
            'total' => $total,
            'actifs' => $actifs,
            'inactifs' => $inactifs,
            'top_villes' => $topVilles,
        ]);

        return $responseParameters;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            EmailField::new('email'),
            TextField::new('role')->hideOnForm(),
            TextField::new('nom'),
            TextField::new('prenom'),
            TextField::new('telephone'),
            DateField::new('dateNaissance')->setRequired(false),
            AssociationField::new('laboratoire')->setRequired(false),
            BooleanField::new('estActif')->setLabel('Disponible'),
        ];
    }
}
