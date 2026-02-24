<?php

namespace App\Controller;

use App\Service\FraudDetectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminFraudController extends AbstractController
{
    #[Route('/fraude', name: 'fraud', methods: ['GET'])]
    public function index(FraudDetectionService $fraudDetectionService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $report = $fraudDetectionService->generateReport();

        return $this->render('admin/fraud/index.html.twig', [
            'signals' => $report['signals'],
            'scores' => $report['scores'],
        ]);
    }
}
