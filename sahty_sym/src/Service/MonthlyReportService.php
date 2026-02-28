<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class MonthlyReportService
{
    private AdminAnalyticsService $analyticsService;
    private Environment $twig;
    public function __construct(AdminAnalyticsService $analyticsService, Environment $twig)
    {
        $this->analyticsService = $analyticsService;
        $this->twig = $twig;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReport(?\DateTimeInterface $month = null): array
    {
        $month = $month ? \DateTimeImmutable::createFromInterface($month) : new \DateTimeImmutable('first day of this month 00:00:00');
        $start = $month->modify('first day of this month 00:00:00');
        $end = $month->modify('last day of this month 23:59:59');

        $snapshot = $this->analyticsService->getSnapshot($start, $end);

        return [
            'title' => 'Rapport mensuel admin',
            'month_label' => $start->format('F Y'),
            'period' => [
                'start' => $start,
                'end' => $end,
            ],
            'snapshot' => $snapshot,
            'generated_at' => new \DateTimeImmutable(),
        ];
    }

    public function renderPdf(array $report): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        $html = $this->twig->render('admin/reports/monthly_report_pdf.html.twig', [
            'report' => $report,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function savePdf(array $report, string $directory): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $filename = sprintf(
            'rapport_admin_%s.pdf',
            $report['period']['start']->format('Y_m')
        );

        $filePath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $pdfContent = $this->renderPdf($report);

        file_put_contents($filePath, $pdfContent);

        return $filePath;
    }
}
