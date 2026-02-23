<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ParapharmacieRepository;
use App\Repository\ProduitRepository;
use App\Entity\Produit;

final class ParapharmacieController extends AbstractController
{
    #[Route('/parapharmacies', name: 'app_parapharmacie_list')]
    public function list(ParapharmacieRepository $parapharmacieRepository): Response
    {
        $parapharmacies = $parapharmacieRepository->findAll();

        return $this->render('parapharmacie/index.html.twig', [
            'parapharmacies' => $parapharmacies,
        ]);
    }

    #[Route('/parapharmacies-produits', name: 'app_parapharmacie_produits')]
    public function listAll(ParapharmacieRepository $parapharmacieRepository, ProduitRepository $produitRepository): Response
    {
        $parapharmacies = $parapharmacieRepository->findAll();
        $produits = $produitRepository->findAll();

        return $this->render('parapharmacie/list_all.html.twig', [
            'parapharmacies' => $parapharmacies,
            'produits' => $produits,
        ]);
    }
    
    #[Route('/produit/{id}', name: 'app_produit_details')]
    public function details(
        Produit $produit,
        ParapharmacieRepository $parapharmacieRepository
    ): Response
    {
        // Récupérer toutes les parapharmacies
        $toutesParapharmacies = $parapharmacieRepository->findAll();
        
        // Récupérer la parapharmacie du produit actuel
        $parapharmacieActuelle = $produit->getParapharmacie();
        
        return $this->render('produit/search_results.html.twig', [
            'produit' => $produit,
            'toutesParapharmacies' => $toutesParapharmacies,
            'parapharmacieActuelle' => $parapharmacieActuelle,
        ]);
    }
}