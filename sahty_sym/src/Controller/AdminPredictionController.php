<?php

namespace App\Controller;

use App\Service\UserPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminPredictionController extends AbstractController
{
    #[Route('/predictions', name: 'predictions', methods: ['GET'])]
    public function index(UserPredictionService $predictionService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $predictions = $predictionService->buildPredictions();

        return $this->render('admin/predictions/index.html.twig', [
            'predictions' => $predictions,
        ]);
    }
}
