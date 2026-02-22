<?php

namespace App\Controller\Admin;

use App\Entity\Laboratoire;
use App\Entity\ResponsableLaboratoire;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->forward('App\\Controller\\AdminController::index');
    }

    #[Route('/admin/laboratoires', name: 'admin_laboratoires')]
    public function laboratoires(AdminUrlGenerator $adminUrlGenerator): Response
    {
        $url = $adminUrlGenerator
            ->setController(LaboratoireCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    #[Route('/admin/responsables-labo', name: 'admin_responsables_labo')]
    public function responsablesLabo(AdminUrlGenerator $adminUrlGenerator): Response
    {
        $url = $adminUrlGenerator
            ->setController(ResponsableLaboratoireCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Backoffice Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Gestion');
        yield MenuItem::linkToCrud('Laboratoires', 'fa fa-flask', Laboratoire::class);
        yield MenuItem::linkToCrud('Responsables labo', 'fa fa-user', ResponsableLaboratoire::class);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('app')
            ->addCssFile('css/admin-dashboard.css')
            ->addJsFile('js/admin-search.js'); // Charge votre script
    }
}
