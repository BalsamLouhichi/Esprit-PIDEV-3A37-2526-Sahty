<?php

namespace App\Controller\Admin;

use App\Entity\ResponsableLaboratoire;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

class ResponsableLaboratoireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ResponsableLaboratoire::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setSearchFields(['nom', 'prenom']);
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
