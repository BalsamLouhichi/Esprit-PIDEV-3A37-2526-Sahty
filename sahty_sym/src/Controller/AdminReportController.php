<?php

namespace App\Controller;

use App\Service\MonthlyReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reports', name: 'admin_reports_')]
class AdminReportController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/reports/index.html.twig');
    }

    #[Route('/monthly', name: 'monthly', methods: ['GET'])]
    public function downloadMonthly(Request $request, MonthlyReportService $reportService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $monthParam = $request->query->get('month');
        $month = null;
        if (is_string($monthParam) && $monthParam !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m', $monthParam);
            if (!$parsed) {
                $this->addFlash('danger', 'Format de mois invalide. Utilisez YYYY-MM.');
                return $this->redirectToRoute('admin_reports_index');
            }
            $month = $parsed->setTime(0, 0)->modify('first day of this month');
        }

        $report = $reportService->buildReport($month);
        $pdfContent = $reportService->renderPdf($report);

        $fileName = sprintf('rapport_admin_%s.pdf', $report['period']['start']->format('Y_m'));

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
}
